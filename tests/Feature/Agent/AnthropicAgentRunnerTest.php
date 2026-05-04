<?php

declare(strict_types=1);

use Bloxy\Core\Agent\AgentRunResult;
use Bloxy\Core\Agent\Concerns\HasDefaultVisibility;
use Bloxy\Core\Agent\Concerns\HasNoTools;
use Bloxy\Core\Agent\LlmAgent;
use Bloxy\Core\Agent\Runners\AnthropicAgentRunner;
use Bloxy\Core\Agent\Runners\AnthropicConfig;
use Bloxy\Core\Tests\Support\Agent\FakeAnthropicTransport;

class SimpleEchoAgent implements LlmAgent
{
    use HasDefaultVisibility;
    use HasNoTools;

    public function name(): string
    {
        return 'demo.echo-llm';
    }

    public function description(): string
    {
        return 'echo via llm';
    }

    public function invoke(array $params): array
    {
        throw new \LogicException('Use AnthropicAgentRunner');
    }

    public function systemPrompt(): string
    {
        return 'You are an echo bot. Repeat the input.';
    }

    public function buildPrompt(array $params): string
    {
        return $params['text'] ?? '';
    }

    public function shapeResult(string $finalText, array $toolHistory): array
    {
        return ['text' => $finalText];
    }
}

it('returns the model final text on a no-tools end_turn response', function () {
    $transport = new FakeAnthropicTransport();
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_test_1',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-4-7',
        'content' => [
            ['type' => 'text', 'text' => 'echo: hello world'],
        ],
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => [
            'input_tokens' => 42,
            'output_tokens' => 7,
            'cache_read_input_tokens' => 0,
            'cache_creation_input_tokens' => 0,
        ],
    ]));

    $config = new AnthropicConfig(apiKey: 'sk-test');
    $runner = AnthropicAgentRunner::createWithTransport($config, $transport);

    $result = $runner->run(new SimpleEchoAgent(), ['text' => 'hello world']);

    expect($result)->toBeInstanceOf(AgentRunResult::class);
    expect($result->result)->toBe(['text' => 'echo: hello world']);
    expect($result->promptTokens)->toBe(42);
    expect($result->completionTokens)->toBe(7);
    expect($result->costUsdCents)->not->toBeNull();
});

class ToolUsingAgent implements \Bloxy\Core\Agent\LlmAgent {
    use \Bloxy\Core\Agent\Concerns\HasDefaultVisibility;
    /** @var array<int, array{0: string, 1: array}> */
    public array $toolCalls = [];
    public function name(): string { return 'demo.tool-using'; }
    public function description(): string { return 'tool user'; }
    public function invoke(array $params): array { throw new \LogicException(); }
    public function systemPrompt(): string { return 'Use tools when relevant.'; }
    public function tools(): array {
        return [new \Bloxy\Core\Agent\Llm\ToolDefinition(
            name: 'lookup',
            description: 'Look up a value',
            inputSchema: [
                'type' => 'object',
                'properties' => ['key' => ['type' => 'string']],
                'required' => ['key'],
            ],
        )];
    }
    public function buildPrompt(array $params): string { return $params['q']; }
    public function handleTool(string $name, array $input): array {
        $this->toolCalls[] = [$name, $input];
        return ['value' => 'looked up [' . $input['key'] . ']'];
    }
    public function shapeResult(string $finalText, array $toolHistory): array {
        return ['text' => $finalText, 'tools_called' => count($toolHistory)];
    }
}

it('runs the tool-use loop until end_turn', function () {
    $transport = new FakeAnthropicTransport();

    // Round 1: model emits tool_use.
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_1',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-4-7',
        'content' => [
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'lookup', 'input' => ['key' => 'foo']],
        ],
        'stop_reason' => 'tool_use',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => [
            'input_tokens' => 100,
            'output_tokens' => 20,
            'cache_read_input_tokens' => 0,
            'cache_creation_input_tokens' => 0,
        ],
    ]));

    // Round 2: model emits final text.
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_2',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-4-7',
        'content' => [['type' => 'text', 'text' => 'looked up foo: looked up [foo]']],
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => [
            'input_tokens' => 130,
            'output_tokens' => 15,
            'cache_read_input_tokens' => 0,
            'cache_creation_input_tokens' => 0,
        ],
    ]));

    $runner = AnthropicAgentRunner::createWithTransport(new AnthropicConfig(apiKey: 'sk-test'), $transport);
    $agent = new ToolUsingAgent();

    $result = $runner->run($agent, ['q' => 'lookup foo']);

    expect($agent->toolCalls)->toBe([['lookup', ['key' => 'foo']]]);
    expect($result->result)->toBe(['text' => 'looked up foo: looked up [foo]', 'tools_called' => 1]);
    // Token meta accumulates across both API calls.
    expect($result->promptTokens)->toBe(230); // 100 + 130
    expect($result->completionTokens)->toBe(35); // 20 + 15

    // Sanity: the second request must include the tool_result.
    $bodies = array_map(fn ($r) => json_decode((string) $r->getBody(), true), $transport->requests());
    expect($bodies[1]['messages'])->toHaveCount(3); // user, assistant (tool_use), user (tool_result)
    expect($bodies[1]['messages'][2]['content'][0]['type'])->toBe('tool_result');
});

it('throws AnthropicInvocationException if the tool-use loop exceeds 25 iterations', function () {
    $transport = new FakeAnthropicTransport();

    // Queue 26 tool_use responses; runner should give up.
    for ($i = 0; $i < 26; $i++) {
        $transport->queue(fake_anthropic_response([
            'id' => "msg_$i",
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-4-7',
            'content' => [['type' => 'tool_use', 'id' => "toolu_$i", 'name' => 'lookup', 'input' => ['key' => "k$i"]]],
            'stop_reason' => 'tool_use',
            'stop_sequence' => null,
            'stop_details' => null,
            'container' => null,
            'usage' => ['input_tokens' => 5, 'output_tokens' => 5, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
        ]));
    }

    $runner = AnthropicAgentRunner::createWithTransport(new AnthropicConfig(apiKey: 'sk-test'), $transport);

    expect(fn () => $runner->run(new ToolUsingAgent(), ['q' => 'spam']))
        ->toThrow(\Bloxy\Core\Agent\Exceptions\AnthropicInvocationException::class);
});

// ---------------------------------------------------------------------------
// Task 7c — prompt caching coverage.
// ---------------------------------------------------------------------------

it('includes cache_control: ephemeral on the request when prompt_cache_enabled is true (default)', function () {
    $transport = new FakeAnthropicTransport();
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_x', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-4-7',
        'content' => [['type' => 'text', 'text' => 'ok']],
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => ['input_tokens' => 5, 'output_tokens' => 1, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
    ]));

    $runner = AnthropicAgentRunner::createWithTransport(new AnthropicConfig(apiKey: 'sk-test'), $transport);
    $runner->run(new SimpleEchoAgent(), ['text' => 'hi']);

    $body = $transport->lastRequestBody();
    expect($body['cache_control'])->toBe(['type' => 'ephemeral']);
});

it('omits cache_control when prompt_cache_enabled is false', function () {
    $transport = new FakeAnthropicTransport();
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_x', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-4-7',
        'content' => [['type' => 'text', 'text' => 'ok']],
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => ['input_tokens' => 5, 'output_tokens' => 1, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
    ]));

    $config = new AnthropicConfig(apiKey: 'sk-test', promptCacheEnabled: false);
    $runner = AnthropicAgentRunner::createWithTransport($config, $transport);
    $runner->run(new SimpleEchoAgent(), ['text' => 'hi']);

    $body = $transport->lastRequestBody();
    expect($body)->not->toHaveKey('cache_control');
});

it('credits cache_read_input_tokens at 0.1x and cache_creation_input_tokens at 1.25x in cost', function () {
    $transport = new FakeAnthropicTransport();
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_x', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-4-7',
        'content' => [['type' => 'text', 'text' => 'ok']],
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => [
            'input_tokens' => 100,
            'output_tokens' => 50,
            'cache_read_input_tokens' => 1_000_000,
            'cache_creation_input_tokens' => 0,
        ],
    ]));

    $runner = AnthropicAgentRunner::createWithTransport(new AnthropicConfig(apiKey: 'sk-test'), $transport);
    $result = $runner->run(new SimpleEchoAgent(), ['text' => 'hi']);

    // 100 input * $5/M + 50 output * $25/M + 1M cache_read * $5/M * 0.1 = ~50 cents.
    expect($result->costUsdCents)->toBeGreaterThan(49)->toBeLessThan(52);
});

// ---------------------------------------------------------------------------
// Task 7d — error mapping coverage.
// ---------------------------------------------------------------------------

it('throws AnthropicMaxTokensException on stop_reason=max_tokens', function () {
    $transport = new FakeAnthropicTransport();
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_x', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-4-7',
        'content' => [['type' => 'text', 'text' => 'incomplete']],
        'stop_reason' => 'max_tokens',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => ['input_tokens' => 5, 'output_tokens' => 4096, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
    ]));
    $runner = AnthropicAgentRunner::createWithTransport(new AnthropicConfig(apiKey: 'sk-test'), $transport);
    expect(fn () => $runner->run(new SimpleEchoAgent(), ['text' => 'hi']))
        ->toThrow(\Bloxy\Core\Agent\Exceptions\AnthropicMaxTokensException::class);
});

it('throws AnthropicRefusalException on stop_reason=refusal', function () {
    $transport = new FakeAnthropicTransport();
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_x', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-4-7',
        'content' => [['type' => 'text', 'text' => 'I cannot help with that.']],
        'stop_reason' => 'refusal',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => ['input_tokens' => 5, 'output_tokens' => 6, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
    ]));
    $runner = AnthropicAgentRunner::createWithTransport(new AnthropicConfig(apiKey: 'sk-test'), $transport);
    expect(fn () => $runner->run(new SimpleEchoAgent(), ['text' => 'hi']))
        ->toThrow(\Bloxy\Core\Agent\Exceptions\AnthropicRefusalException::class);
});

it('throws AnthropicContextOverflowException on stop_reason=model_context_window_exceeded', function () {
    $transport = new FakeAnthropicTransport();
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_x', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-4-7',
        'content' => [],
        'stop_reason' => 'model_context_window_exceeded',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => ['input_tokens' => 999999, 'output_tokens' => 0, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
    ]));
    $runner = AnthropicAgentRunner::createWithTransport(new AnthropicConfig(apiKey: 'sk-test'), $transport);
    expect(fn () => $runner->run(new SimpleEchoAgent(), ['text' => 'hi']))
        ->toThrow(\Bloxy\Core\Agent\Exceptions\AnthropicContextOverflowException::class);
});

it('throws AnthropicInvocationException on a stop_reason the runner does not specially handle (stop_sequence)', function () {
    // SDK StopReason enum accepts: end_turn, max_tokens, stop_sequence, tool_use, pause_turn, refusal.
    // The runner specially handles end_turn / tool_use / pause_turn, and maps max_tokens / refusal /
    // model_context_window_exceeded to typed exceptions; stop_sequence falls through to the default.
    $transport = new FakeAnthropicTransport();
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_x', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-4-7',
        'content' => [['type' => 'text', 'text' => 'partial']],
        'stop_reason' => 'stop_sequence',
        'stop_sequence' => 'STOP',
        'stop_details' => null,
        'container' => null,
        'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
    ]));
    $runner = AnthropicAgentRunner::createWithTransport(new AnthropicConfig(apiKey: 'sk-test'), $transport);
    expect(fn () => $runner->run(new SimpleEchoAgent(), ['text' => 'hi']))
        ->toThrow(\Bloxy\Core\Agent\Exceptions\AnthropicInvocationException::class);
});

it('passes a tool result with is_error=true when the agent throws inside handleTool', function () {
    $transport = new FakeAnthropicTransport();
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-4-7',
        'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'lookup', 'input' => ['key' => 'bad']]],
        'stop_reason' => 'tool_use',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => ['input_tokens' => 50, 'output_tokens' => 10, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
    ]));
    $transport->queue(fake_anthropic_response([
        'id' => 'msg_2', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-4-7',
        'content' => [['type' => 'text', 'text' => 'sorry, lookup failed']],
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'usage' => ['input_tokens' => 70, 'output_tokens' => 8, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
    ]));

    $agent = new class extends ToolUsingAgent {
        public function handleTool(string $name, array $input): array {
            throw new \RuntimeException('lookup unavailable');
        }
    };

    $runner = AnthropicAgentRunner::createWithTransport(new AnthropicConfig(apiKey: 'sk-test'), $transport);
    $result = $runner->run($agent, ['q' => 'lookup bad']);

    expect($result->result['text'])->toBe('sorry, lookup failed');

    $bodies = array_map(fn ($r) => json_decode((string) $r->getBody(), true), $transport->requests());
    $toolResultBlock = $bodies[1]['messages'][2]['content'][0];
    expect($toolResultBlock['type'])->toBe('tool_result');
    expect($toolResultBlock['is_error'])->toBeTrue();
});
