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
