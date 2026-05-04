<?php

declare(strict_types=1);

use Bloxy\Core\BloxyCoreServiceProvider;
use Illuminate\Contracts\Foundation\Application;

it('registers the BloxyCoreServiceProvider with the application', function () {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(BloxyCoreServiceProvider::class);
    expect($providers[BloxyCoreServiceProvider::class])->toBeTrue();
});

it('binds the application correctly via the package provider', function () {
    /** @var Application $app */
    $app = app();
    expect($app)->toBeInstanceOf(Application::class);
});

it('binds the Redactor as a singleton seeded from config', function () {
    $a = app(\Bloxy\Core\Observability\Redactor::class);
    $b = app(\Bloxy\Core\Observability\Redactor::class);

    expect($a)->toBe($b);

    $result = $a->redact(['password' => 'sekret', 'name' => 'nat']);
    expect($result)->toBe(['password' => '[REDACTED]', 'name' => 'nat']);
});

it('registers the audit middleware alias on the router', function () {
    /** @var \Illuminate\Routing\Router $router */
    $router = app(\Illuminate\Routing\Router::class);

    $aliases = $router->getMiddleware();

    expect($aliases)->toHaveKey('bloxy.audit');
    expect($aliases['bloxy.audit'])->toBe(\Bloxy\Core\Audit\AuditMiddleware::class);
});

it('binds BloxyAccessResolver as a singleton', function () {
    $a = app(\Bloxy\Core\Rbac\BloxyAccessResolver::class);
    $b = app(\Bloxy\Core\Rbac\BloxyAccessResolver::class);
    expect($a)->toBeInstanceOf(\Bloxy\Core\Rbac\BloxyAccessResolver::class);
    expect($a)->toBe($b);
});

it('exposes the bloxy.rbac config namespace', function () {
    expect(config('bloxy.rbac'))->toBeArray();
    expect(config('bloxy.rbac'))->toHaveKey('predicate_evaluator');
});

it('binds AgentRunner to NaiveRunner by default', function () {
    expect(app(\Bloxy\Core\Agent\AgentRunner::class))
        ->toBeInstanceOf(\Bloxy\Core\Agent\Runners\NaiveRunner::class);
});

it('binds AgentAuthorizer to BloxyRbacAgentAuthorizer by default (hard-required RBAC)', function () {
    expect(app(\Bloxy\Core\Agent\AgentAuthorizer::class))
        ->toBeInstanceOf(\Bloxy\Core\Agent\Authorizers\BloxyRbacAgentAuthorizer::class);
});

it('UsageLogger resolves as a singleton', function () {
    expect(app(\Bloxy\Core\Agent\UsageLog\UsageLogger::class))
        ->toBe(app(\Bloxy\Core\Agent\UsageLog\UsageLogger::class));
});

it('AgentRunner resolves as a singleton', function () {
    expect(app(\Bloxy\Core\Agent\AgentRunner::class))
        ->toBe(app(\Bloxy\Core\Agent\AgentRunner::class));
});

it('AgentAuthorizer resolves as a singleton', function () {
    expect(app(\Bloxy\Core\Agent\AgentAuthorizer::class))
        ->toBe(app(\Bloxy\Core\Agent\AgentAuthorizer::class));
});

it('does not bind AnthropicAgentRunner by default — consumers opt in', function () {
    expect(app(\Bloxy\Core\Agent\AgentRunner::class))
        ->toBeInstanceOf(\Bloxy\Core\Agent\Runners\NaiveRunner::class);
    expect(app(\Bloxy\Core\Agent\AgentRunner::class))
        ->not->toBeInstanceOf(\Bloxy\Core\Agent\Runners\AnthropicAgentRunner::class);
});

it('resolves AnthropicAgentRunner from container when consumers bind it explicitly', function () {
    $config = new \Bloxy\Core\Agent\Runners\AnthropicConfig(apiKey: 'sk-test');
    app()->instance(\Bloxy\Core\Agent\Runners\AnthropicConfig::class, $config);
    app()->bind(\Bloxy\Core\Agent\AgentRunner::class, function () use ($config) {
        return \Bloxy\Core\Agent\Runners\AnthropicAgentRunner::createWithTransport(
            $config,
            new \Bloxy\Core\Tests\Support\Agent\FakeAnthropicTransport(),
        );
    });

    expect(app(\Bloxy\Core\Agent\AgentRunner::class))
        ->toBeInstanceOf(\Bloxy\Core\Agent\Runners\AnthropicAgentRunner::class);
});
