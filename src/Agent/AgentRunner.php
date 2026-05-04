<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent;

/**
 * Dispatch primitive for an agent invocation.
 *
 * The default binding (NaiveRunner) just calls $agent->invoke($params)
 * and wraps in AgentRunResult. The Anthropic-backed runner (M2 Sub-plan B-1)
 * fills token + cost meta on the result so the registry can pass real
 * numbers to UsageLogger. Apps wire their own runner by binding
 * AgentRunner::class to a different concrete in their service provider.
 */
interface AgentRunner
{
    /**
     * @param array<string, mixed> $params
     */
    public function run(Agent $agent, array $params): AgentRunResult;
}
