<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Runners;

use Anthropic\Client as AnthropicClient;
use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ToolUseBlock;
use Anthropic\RequestOptions;
use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentRunner;
use Bloxy\Core\Agent\AgentRunResult;
use Bloxy\Core\Agent\Exceptions\AnthropicContextOverflowException;
use Bloxy\Core\Agent\Exceptions\AnthropicInvocationException;
use Bloxy\Core\Agent\Exceptions\AnthropicMaxTokensException;
use Bloxy\Core\Agent\Exceptions\AnthropicRefusalException;
use Bloxy\Core\Agent\LlmAgent;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;

/**
 * Runs an LlmAgent against the Anthropic Messages API.
 *
 * Sub-task 7a (this commit): no-tools end_turn happy path only — single
 * round-trip, extract the final text from the first text block, accumulate
 * usage, compute cost. Tool-use loop (7b), prompt-caching coverage (7c),
 * and full error mapping (7d) follow in separate commits.
 */
final class AnthropicAgentRunner implements AgentRunner
{
    private const MAX_ITERATIONS = 25;

    public function __construct(
        private readonly AnthropicClient $client,
        private readonly AnthropicConfig $config,
    ) {}

    /**
     * Build a runner with a custom PSR-18 transport. Used by tests via
     * FakeAnthropicTransport, and by apps that want to inject a Guzzle
     * client with custom middleware (timeouts, retries, telemetry).
     */
    public static function createWithTransport(AnthropicConfig $config, ClientInterface $transport): self
    {
        $client = new AnthropicClient(
            apiKey: $config->apiKey,
            requestOptions: RequestOptions::with(transporter: $transport),
        );

        return new self($client, $config);
    }

    public function run(Agent $agent, array $params): AgentRunResult
    {
        if (! $agent instanceof LlmAgent) {
            throw new InvalidArgumentException(sprintf(
                'AnthropicAgentRunner requires an LlmAgent; got [%s].',
                $agent::class,
            ));
        }

        $messages = [[
            'role' => 'user',
            'content' => [['type' => 'text', 'text' => $agent->buildPrompt($params)]],
        ]];

        $usage = ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0];
        $toolHistory = [];

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
            $response = $this->callMessages($agent, $messages);
            $this->accumulateUsage($usage, $response);

            $rawStop = $response['stopReason'] ?? null;
            $stopReason = $rawStop instanceof \BackedEnum ? (string) $rawStop->value : (string) ($rawStop ?? '');

            if ($stopReason === 'end_turn') {
                $finalText = $this->extractFinalText($response);

                return new AgentRunResult(
                    result: $agent->shapeResult($finalText, $toolHistory),
                    promptTokens: $usage['input'] + $usage['cache_read'] + $usage['cache_write'],
                    completionTokens: $usage['output'],
                    costUsdCents: Pricing::costUsdCentsFor(
                        $this->config->model,
                        inputTokens: $usage['input'],
                        outputTokens: $usage['output'],
                        cacheReadTokens: $usage['cache_read'],
                        cacheWriteTokens: $usage['cache_write'],
                    ),
                );
            }

            if ($stopReason === 'tool_use') {
                // Append assistant turn (full original content) so the model has its own
                // tool_use blocks in context, then run each tool and append the
                // tool_result blocks as a single user turn.
                $assistantContent = $this->serializeAssistantContent($response);
                $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

                $toolResultBlocks = [];
                foreach ($response->content as $block) {
                    if (! $block instanceof ToolUseBlock) {
                        continue;
                    }

                    $toolUseId = $block->id;
                    $toolName = $block->name;
                    $toolInput = $block->input;

                    $isError = false;
                    try {
                        $rawResult = $agent->handleTool($toolName, $toolInput);
                        $resultContent = is_string($rawResult) ? $rawResult : json_encode($rawResult, JSON_THROW_ON_ERROR);
                    } catch (\Throwable $e) {
                        $isError = true;
                        $rawResult = ['error' => $e->getMessage()];
                        $resultContent = $e->getMessage();
                    }

                    $toolHistory[] = [
                        'name' => $toolName,
                        'input' => $toolInput,
                        'result' => $rawResult,
                        'is_error' => $isError,
                    ];

                    $toolResultBlocks[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $toolUseId,
                        'content' => $resultContent,
                        'is_error' => $isError,
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $toolResultBlocks];
                continue;
            }

            if ($stopReason === 'pause_turn') {
                // Server-side tool use: append assistant turn and let the API resume.
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $this->serializeAssistantContent($response),
                ];
                continue;
            }

            // max_tokens, refusal, model_context_window_exceeded, or anything unexpected.
            $this->mapStopReasonToException($agent->name(), $stopReason);
        }

        throw AnthropicInvocationException::forStopReason($agent->name(), 'iteration_cap_exceeded');
    }

    /**
     * Re-emit the model's content blocks back to the wire shape so we can
     * forward the assistant turn into the next request. The SDK's typed
     * blocks (TextBlock / ToolUseBlock) carry extra fields (e.g. caller)
     * we don't want to round-trip, so we hand-build the wire form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeAssistantContent(Message $response): array
    {
        $out = [];
        foreach ($response->content as $block) {
            if ($block instanceof TextBlock) {
                $out[] = ['type' => 'text', 'text' => $block->text];
                continue;
            }

            if ($block instanceof ToolUseBlock) {
                $out[] = [
                    'type' => 'tool_use',
                    'id' => $block->id,
                    'name' => $block->name,
                    'input' => $block->input === [] ? new \stdClass() : $block->input,
                ];
                continue;
            }

            // Unknown block — best-effort pass-through via array access.
            if (is_object($block) && method_exists($block, 'jsonSerialize')) {
                /** @var array<string, mixed> $serialized */
                $serialized = $block->jsonSerialize();
                $out[] = $serialized;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function callMessages(LlmAgent $agent, array $messages): Message
    {
        $args = [
            'maxTokens' => $this->config->maxTokens,
            'messages' => $messages,
            'model' => $this->config->model,
            'system' => $agent->systemPrompt(),
            'outputConfig' => ['effort' => $this->config->effort],
        ];

        if ($this->config->thinking !== 'disabled') {
            $args['thinking'] = ['type' => 'adaptive'];
        }

        $tools = $agent->tools();
        if ($tools !== []) {
            $args['tools'] = array_map(static fn ($t) => $t->toAnthropicArray(), $tools);
        }

        if ($this->config->promptCacheEnabled) {
            $args['cacheControl'] = ['type' => 'ephemeral'];
        }

        return $this->client->messages->create(...$args);
    }

    /**
     * @param  array{input:int,output:int,cache_read:int,cache_write:int}  $usage
     */
    private function accumulateUsage(array &$usage, Message $response): void
    {
        $u = $response->usage;
        $usage['input'] += $u->inputTokens;
        $usage['output'] += $u->outputTokens;
        $usage['cache_read'] += $u->cacheReadInputTokens ?? 0;
        $usage['cache_write'] += $u->cacheCreationInputTokens ?? 0;
    }

    private function extractFinalText(Message $response): string
    {
        foreach ($response->content as $block) {
            if ($block instanceof TextBlock) {
                return $block->text;
            }
        }

        return '';
    }

    private function mapStopReasonToException(string $agentName, string $stopReason): never
    {
        match ($stopReason) {
            'max_tokens' => throw AnthropicMaxTokensException::for($agentName, $this->config->maxTokens),
            'refusal' => throw AnthropicRefusalException::for($agentName, '(no model-stated reason yet)'),
            'model_context_window_exceeded' => throw AnthropicContextOverflowException::for($agentName),
            default => throw AnthropicInvocationException::forStopReason($agentName, $stopReason),
        };
    }
}
