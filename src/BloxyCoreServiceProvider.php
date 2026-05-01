<?php

declare(strict_types=1);

namespace Bloxy\Core;

use Bloxy\Core\Audit\AuditMiddleware;
use Bloxy\Core\Observability\Redactor;
use Bloxy\Core\Rbac\BloxyAccessResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
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

        $this->app->singleton(BloxyAccessResolver::class, function () {
            return new BloxyAccessResolver();
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->registerAuditMiddleware();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bloxy.php' => config_path('bloxy.php'),
            ], 'bloxy-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'bloxy-migrations');
        }
    }

    private function registerAuditMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $alias = (string) $this->app['config']->get('bloxy.audit.middleware_alias', 'bloxy.audit');

        $router->aliasMiddleware($alias, AuditMiddleware::class);
    }
}
