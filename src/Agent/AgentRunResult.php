<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent;

/**
 * Return value from AgentRunner::run().
 *
 * Carries the agent's user-facing result alongside optional measured token
 * usage + cost in cents. NaiveRunner returns null token fields. Runners
 * that talk to LLMs (AnthropicAgentRunner) fill them so the registry can
 * pass real numbers to UsageLogger.
 */
final class AgentRunResult
{
    /** @param array<string, mixed> $result */
    public function __construct(
        public readonly array $result,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?int $costUsdCents = null,
    ) {}
}
