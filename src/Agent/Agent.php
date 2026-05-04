<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent;

/**
 * The contract every BLOXY agent must implement.
 *
 * Agents are registered at boot via AgentRegistry::register(). The registry
 * stores the agent definitions and exposes them for listing in UIs (e.g.,
 * the cockpit/portal "registered agent surface" the README describes).
 *
 * In M2 Sub-plan A, invoke() exists on the contract for forward
 * compatibility but is unreachable — the registry's invoke() throws before
 * this method is called. M2 Sub-plan B will define dispatch semantics.
 */
interface Agent
{
    /**
     * Stable identifier, used as the registry key.
     * Convention: dot-separated, e.g. "tracy.summarize-vault".
     */
    public function name(): string;

    /** Human-readable one-liner shown in registered-agent UIs. */
    public function description(): string;

    /**
     * Invoke the agent with the supplied parameters.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function invoke(array $params): array;
}
