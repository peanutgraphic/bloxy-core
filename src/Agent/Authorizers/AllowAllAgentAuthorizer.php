<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Authorizers;

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentAuthorizer;

/**
 * Permits every invocation. Apps bind this to opt out of the default
 * hard-required RBAC enforcement.
 */
final class AllowAllAgentAuthorizer implements AgentAuthorizer
{
    /**
     * Always permits. $params is intentionally ignored — opt-out short-circuit.
     */
    public function mayInvoke(Agent $agent, array $params): bool
    {
        return true;
    }
}
