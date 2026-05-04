<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentRegistry;
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

it('binds the registry as a singleton across multiple app(AgentRegistry::class) calls', function () {
    expect(app(AgentRegistry::class))->toBe(app(AgentRegistry::class));
});

it('exposes a visibleIn() default of cockpit + portal via HasDefaultVisibility', function () {
    $agent = new RegistryTestAgent('demo.visible', 'visibility default');
    expect($agent->visibleIn())->toBe(['cockpit', 'portal']);
});

class RegistryTestAgent implements Agent
{
    use \Bloxy\Core\Agent\Concerns\HasDefaultVisibility;

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
