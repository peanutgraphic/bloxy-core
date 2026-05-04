<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent;

use Bloxy\Core\Agent\Llm\ToolDefinition;

/**
 * Sub-interface for agents intended to run via an LLM-backed AgentRunner
 * (e.g., AnthropicAgentRunner). The runner orchestrates the tool-use loop,
 * calling the methods below at the appropriate points.
 *
 * For the NaiveRunner, an LlmAgent's invoke() is still used directly —
 * but most LlmAgent implementations don't make sense without a real runner,
 * so they typically throw from invoke().
 */
interface LlmAgent extends Agent
{
    /** Stable system prompt sent to the model. Cached if prompt caching is on. */
    public function systemPrompt(): string;

    /**
     * Tools the model may call. Empty array means "no tools, single-shot
     * generation". Cached alongside the system prompt.
     *
     * @return array<ToolDefinition>
     */
    public function tools(): array;

    /**
     * Build the initial user message text from the runner's $params.
     * The runner sends this as a single text content block in messages[0].
     */
    public function buildPrompt(array $params): string;

    /**
     * Execute one tool call from the model and return the result content
     * the runner should send back as a tool_result block. The result is
     * JSON-encoded into a text block. Throw to signal a tool-execution
     * failure — the runner will surface it as is_error=true to the model.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|string
     */
    public function handleTool(string $name, array $input): array|string;

    /**
     * Shape the final result returned to the registry from the model's
     * final text. Default impl can return ['text' => $finalText] but agents
     * are free to parse / structure however they want.
     *
     * @param array<array<string, mixed>> $toolHistory  one entry per tool_use round
     * @return array<string, mixed>
     */
    public function shapeResult(string $finalText, array $toolHistory): array;
}
