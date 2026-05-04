<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentRegistry;
use Bloxy\Core\Agent\Exceptions\AgentInvocationNotImplementedException;
use Bloxy\Core\Agent\Exceptions\AgentNameConflictException;
use Bloxy\Core\Agent\InMemoryAgentRegistry;

it('exposes the InMemoryAgentRegistry as the default binding', function () {
    expect(app(AgentRegistry::class))->toBeInstanceOf(InMemoryAgentRegistry::class);
});

it('registers an agent and finds it by name', function () {
    $registry = app(AgentRegistry::class);
    $agent = new RegistryTestAgent('demo.echo', 'Echoes its input');

    $registry->register($agent);

    expect($registry->find('demo.echo'))->toBe($agent);
});

it('returns null from find for unknown names', function () {
    $registry = app(AgentRegistry::class);
    expect($registry->find('does-not-exist'))->toBeNull();
});

it('returns all registered agents in registration order', function () {
    $registry = app(AgentRegistry::class);
    $a = new RegistryTestAgent('demo.first', 'first');
    $b = new RegistryTestAgent('demo.second', 'second');
    $c = new RegistryTestAgent('demo.third', 'third');

    $registry->register($a);
    $registry->register($b);
    $registry->register($c);

    expect(array_keys($registry->all()))->toBe(['demo.first', 'demo.second', 'demo.third']);
});

it('throws AgentNameConflictException on duplicate registration', function () {
    $registry = app(AgentRegistry::class);
    $registry->register(new RegistryTestAgent('demo.dup', 'first'));

    expect(fn () => $registry->register(new RegistryTestAgent('demo.dup', 'second')))
        ->toThrow(AgentNameConflictException::class);
});

it('throws AgentInvocationNotImplementedException with a clear message', function () {
    $registry = app(AgentRegistry::class);
    $registry->register(new RegistryTestAgent('demo.invoke', 'wont run'));

    expect(fn () => $registry->invoke('demo.invoke', []))
        ->toThrow(AgentInvocationNotImplementedException::class, 'Sub-plan B');
});

it('binds the registry as a singleton across multiple app(AgentRegistry::class) calls', function () {
    expect(app(AgentRegistry::class))->toBe(app(AgentRegistry::class));
});

class RegistryTestAgent implements Agent
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function invoke(array $params): array
    {
        return $params;
    }
}
