<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Runners;

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentRunner;
use Bloxy\Core\Agent\AgentRunResult;

final class NaiveRunner implements AgentRunner
{
    public function run(Agent $agent, array $params): AgentRunResult
    {
        return new AgentRunResult($agent->invoke($params));
    }
}
