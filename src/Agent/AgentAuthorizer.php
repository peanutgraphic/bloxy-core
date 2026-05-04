<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent;

/**
 * Decides whether the current request may invoke a given agent.
 *
 * The default binding (BloxyRbacAgentAuthorizer) hard-requires the
 * 'agent.invoke.{name}' permission via the BLOXY RBAC stack — denying
 * unauthenticated requests and any user who lacks the per-agent grant.
 *
 * Apps that don't want RBAC enforcement can opt out by binding
 * AllowAllAgentAuthorizer (or any custom AgentAuthorizer) in their own
 * service provider.
 */
interface AgentAuthorizer
{
    /**
     * Decide whether the current request may invoke the given agent.
     *
     * The default BloxyRbacAgentAuthorizer ignores $params (the unit of
     * grant is the agent name). $params is reserved for resource-scoped
     * authorizers that need to consult the call's arguments — e.g. a
     * "may invoke summarize-vault on vault N" check.
     *
     * @param array<string, mixed> $params
     */
    public function mayInvoke(Agent $agent, array $params): bool;
}
