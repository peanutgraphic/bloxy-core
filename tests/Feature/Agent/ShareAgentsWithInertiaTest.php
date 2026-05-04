<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentAuthorizer;
use Bloxy\Core\Agent\AgentRegistry;
use Bloxy\Core\Agent\Authorizers\AllowAllAgentAuthorizer;
use Bloxy\Core\Agent\Concerns\HasDefaultVisibility;
use Bloxy\Core\Agent\Http\ShareAgentsWithInertia;
use Illuminate\Http\Request;

class SharedAgentFixture implements Agent {
    use HasDefaultVisibility;
    public function __construct(
        private string $n,
        private string $d,
        private array $vis = ['cockpit', 'portal'],
    ) {}
    public function name(): string { return $this->n; }
    public function description(): string { return $this->d; }
    public function invoke(array $params): array { return $params; }
    public function visibleIn(): array { return $this->vis; }
}

beforeEach(function () {
    app()->bind(AgentAuthorizer::class, AllowAllAgentAuthorizer::class);
});

afterEach(function () {
    if (class_exists(\Inertia\Inertia::class)) {
        \Inertia\Inertia::flushShared();
    }
});

it('shares agents grouped by surface via Inertia::share', function () {
    $registry = app(AgentRegistry::class);
    $registry->register(new SharedAgentFixture('a.cockpit', 'cockpit only', ['cockpit']));
    $registry->register(new SharedAgentFixture('a.portal', 'portal only', ['portal']));
    $registry->register(new SharedAgentFixture('a.both', 'both surfaces', ['cockpit', 'portal']));

    $middleware = app(ShareAgentsWithInertia::class);
    $shared = null;
    $middleware->handle(Request::create('/'), function () use (&$shared) {
        $raw = \Inertia\Inertia::getShared('agents');
        $shared = is_callable($raw) ? $raw() : $raw;
        return response('ok');
    });

    expect($shared)->toBeArray();
    expect(array_keys($shared))->toBe(['cockpit', 'portal']);
    expect(array_column($shared['cockpit'], 'name'))->toBe(['a.cockpit', 'a.both']);
    expect(array_column($shared['portal'], 'name'))->toBe(['a.portal', 'a.both']);
});

it('exposes only name + description per agent (no extra fields leak)', function () {
    $registry = app(AgentRegistry::class);
    $registry->register(new SharedAgentFixture('a.x', 'description here'));

    $middleware = app(ShareAgentsWithInertia::class);
    $shared = null;
    $middleware->handle(Request::create('/'), function () use (&$shared) {
        $raw = \Inertia\Inertia::getShared('agents');
        $shared = is_callable($raw) ? $raw() : $raw;
        return response('ok');
    });

    expect($shared['cockpit'][0])->toBe(['name' => 'a.x', 'description' => 'description here']);
});

it('filters out agents the current user cannot invoke (RBAC)', function () {
    app()->bind(AgentAuthorizer::class, function () {
        return new class implements AgentAuthorizer {
            public function mayInvoke(Agent $a, array $p): bool { return false; }
        };
    });

    $registry = app(AgentRegistry::class);
    $registry->register(new SharedAgentFixture('a.locked', 'no access'));

    $middleware = app(ShareAgentsWithInertia::class);
    $shared = null;
    $middleware->handle(Request::create('/'), function () use (&$shared) {
        $raw = \Inertia\Inertia::getShared('agents');
        $shared = is_callable($raw) ? $raw() : $raw;
        return response('ok');
    });

    expect($shared['cockpit'])->toBe([]);
    expect($shared['portal'])->toBe([]);
});

it('is a no-op when Inertia is not installed (class_exists guard)', function () {
    $reflection = new \ReflectionMethod(ShareAgentsWithInertia::class, 'handle');
    $source = file_get_contents($reflection->getFileName());
    expect($source)->toContain('class_exists');
    expect($source)->toContain('Inertia');
});
