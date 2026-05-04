<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Runners;

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentRunner;

final class NaiveRunner implements AgentRunner
{
    public function run(Agent $agent, array $params): array
    {
        return $agent->invoke($params);
    }
}
