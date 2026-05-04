<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentRegistry;

class SurfaceAgent implements Agent {
    public function __construct(private string $n, private array $vis) {}
    public function name(): string { return $this->n; }
    public function description(): string { return 'surface'; }
    public function invoke(array $params): array { return $params; }
    public function visibleIn(): array { return $this->vis; }
}

it('forSurface filters agents by their visibleIn() set', function () {
    $registry = app(AgentRegistry::class);
    $registry->register(new SurfaceAgent('a.cockpit-only', ['cockpit']));
    $registry->register(new SurfaceAgent('a.portal-only', ['portal']));
    $registry->register(new SurfaceAgent('a.both', ['cockpit', 'portal']));

    expect(array_keys($registry->forSurface('cockpit')))->toBe(['a.cockpit-only', 'a.both']);
    expect(array_keys($registry->forSurface('portal')))->toBe(['a.portal-only', 'a.both']);
    expect($registry->forSurface('unknown'))->toBe([]);
});
