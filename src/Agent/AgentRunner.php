<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent;

/**
 * Dispatch primitive for an agent invocation.
 *
 * The default binding (NaiveRunner) just calls $agent->invoke($params).
 * The Anthropic-backed runner (M2 Sub-plan B-1) ships in a follow-up PR.
 * Apps wire their own runner by binding AgentRunner::class to a different
 * concrete in their service provider.
 */
interface AgentRunner
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function run(Agent $agent, array $params): array;
}
