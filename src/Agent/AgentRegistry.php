<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent;

interface AgentRegistry
{
    /**
     * @throws Exceptions\AgentNameConflictException if an agent with the same name() is already registered.
     */
    public function register(Agent $agent): void;

    /** @return array<string, Agent> All registered agents, keyed by name. Order is registration order. */
    public function all(): array;

    public function find(string $name): ?Agent;

    /**
     * @return array<string, Agent> Agents whose visibleIn() contains $surface, in registration order.
     */
    public function forSurface(string $surface): array;

    /**
     * Invoke a registered agent by name.
     *
     * Authorization runs first via the bound AgentAuthorizer (default
     * BloxyRbacAgentAuthorizer requires permission 'agent.invoke.{name}').
     * Successful invocations emit AGENT_INVOKE audit rows. Denials emit
     * AGENT_INVOKE_DENIED. Agent-thrown exceptions emit AGENT_INVOKE_FAILED
     * and are wrapped in AgentInvocationFailedException.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws Exceptions\AgentNotFoundException
     * @throws Exceptions\AgentAuthorizationDeniedException
     * @throws Exceptions\AgentInvocationFailedException
     */
    public function invoke(string $name, array $params): array;
}
