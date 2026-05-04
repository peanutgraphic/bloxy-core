<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\UsageLog;

use Bloxy\Core\Agent\Agent;
use Illuminate\Support\Carbon;

/**
 * Writes one row per agent invocation to the agent_usage_log table.
 *
 * The default NaiveRunner records null token / cost fields. The
 * AnthropicAgentRunner (M2 Sub-plan B-1) records real values.
 */
class UsageLogger
{
    public function record(
        Agent $agent,
        string $runnerClass,
        ?string $actorType,
        ?string $actorId,
        string $outcome,
        ?int $promptTokens,
        ?int $completionTokens,
        ?int $costUsdCents,
    ): void {
        AgentUsageLog::query()->create([
            'happened_at' => Carbon::now(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'agent_name' => $agent->name(),
            'runner_class' => $runnerClass,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost_usd_cents' => $costUsdCents,
            'outcome' => $outcome,
        ]);
    }
}
