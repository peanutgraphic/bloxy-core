<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent;

/**
 * The agent registry contract. The default binding (InMemoryAgentRegistry)
 * is provided by BloxyCoreServiceProvider. Apps may override by binding a
 * different concrete in their own service providers.
 */
interface AgentRegistry
{
    /**
     * Register an agent.
     *
     * @throws Exceptions\AgentNameConflictException if an agent with the same name() is already registered.
     */
    public function register(Agent $agent): void;

    /**
     * @return array<string, Agent> All registered agents, keyed by name. Order is registration order.
     */
    public function all(): array;

    /**
     * @return ?Agent The agent registered under that name, or null if absent.
     */
    public function find(string $name): ?Agent;

    /**
     * Invoke a registered agent by name.
     *
     * In M2 Sub-plan A: ALWAYS throws AgentInvocationNotImplementedException.
     * Sub-plan B will define the actual dispatch semantics.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws Exceptions\AgentInvocationNotImplementedException always (Sub-plan A).
     */
    public function invoke(string $name, array $params): array;
}
