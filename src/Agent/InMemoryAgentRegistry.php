<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent;

use Bloxy\Core\Agent\Exceptions\AgentInvocationNotImplementedException;
use Bloxy\Core\Agent\Exceptions\AgentNameConflictException;

/**
 * In-memory registry. The default binding for AgentRegistry::class via
 * BloxyCoreServiceProvider. Storage is per-process; restart resets the
 * registered set, which is the correct model for boot-time registration.
 */
final class InMemoryAgentRegistry implements AgentRegistry
{
    /** @var array<string, Agent> */
    private array $agents = [];

    public function register(Agent $agent): void
    {
        $name = $agent->name();
        if (isset($this->agents[$name])) {
            throw AgentNameConflictException::for($name);
        }
        $this->agents[$name] = $agent;
    }

    public function all(): array
    {
        return $this->agents;
    }

    public function find(string $name): ?Agent
    {
        return $this->agents[$name] ?? null;
    }

    public function invoke(string $name, array $params): array
    {
        throw AgentInvocationNotImplementedException::forRegistry();
    }
}
