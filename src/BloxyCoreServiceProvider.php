<?php

declare(strict_types=1);

namespace Bloxy\Core;

use Bloxy\Core\Observability\Redactor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class BloxyCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/bloxy.php',
            'bloxy'
        );

        $this->app->singleton(Redactor::class, function (Application $app) {
            $config = $app['config']->get('bloxy.observability.redaction', [
                'allowlist' => [],
                'marker' => '[REDACTED]',
            ]);

            return new Redactor(
                allowlist: (array) ($config['allowlist'] ?? []),
                marker: (string) ($config['marker'] ?? '[REDACTED]'),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bloxy.php' => config_path('bloxy.php'),
            ], 'bloxy-config');
        }
    }
}
