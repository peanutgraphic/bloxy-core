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
