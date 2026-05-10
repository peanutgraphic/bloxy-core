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
    private static bool $sentryRedactorWired = false;

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

        // Zero-arg constructors — Laravel's container auto-resolves these
        // without an explicit closure. Interface→class bindings ditto.
        $this->app->singleton(BloxyAccessResolver::class);
        $this->app->singleton(\Bloxy\Core\Audit\ChainSigner::class);
        $this->app->singleton(
            \Bloxy\Core\Agent\AgentRunner::class,
            \Bloxy\Core\Agent\Runners\NaiveRunner::class,
        );

        // To opt into AnthropicAgentRunner instead of NaiveRunner, see
        // docs/agents.md § "Wiring AnthropicAgentRunner".

        $this->app->singleton(\Bloxy\Core\Agent\AgentAuthorizer::class, function (Application $app) {
            return new \Bloxy\Core\Agent\Authorizers\BloxyRbacAgentAuthorizer(
                $app->make(\Illuminate\Contracts\Auth\Factory::class),
                $app->make(BloxyAccessResolver::class),
            );
        });

        $this->app->singleton(\Bloxy\Core\Agent\UsageLog\UsageLogger::class);

        $this->app->singleton(\Bloxy\Core\Agent\AgentRegistry::class, function (Application $app) {
            return new \Bloxy\Core\Agent\InMemoryAgentRegistry(
                $app->make(\Bloxy\Core\Agent\AgentAuthorizer::class),
                $app->make(\Bloxy\Core\Agent\AgentRunner::class),
                $app->make(\Bloxy\Core\Agent\UsageLog\UsageLogger::class),
                $app->make(Redactor::class),
                $app->make(\Illuminate\Contracts\Auth\Factory::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->registerAuditMiddleware();
        $this->wireRedactor();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bloxy.php' => config_path('bloxy.php'),
            ], 'bloxy-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'bloxy-migrations');

            $this->commands([
                \Bloxy\Core\Audit\Console\VerifyChainCommand::class,
                \Bloxy\Core\Audit\Console\AuditAnchorCommand::class,
                \Bloxy\Core\Audit\Console\AuditCoverageCommand::class,
            ]);
        }
    }

    private function registerAuditMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $alias = (string) $this->app['config']->get('bloxy.audit.middleware_alias', 'bloxy.audit');

        $router->aliasMiddleware($alias, AuditMiddleware::class);
    }

    private function wireRedactor(): void
    {
        $config = $this->app['config']->get('bloxy.observability.redaction', []);

        if (($config['auto_wire_monolog'] ?? true) === true) {
            $this->wireMonolog();
        }

        if (($config['auto_wire_sentry'] ?? true) === true && class_exists('Sentry\\State\\Hub')) {
            $this->wireSentry();
        }
    }

    private function wireMonolog(): void
    {
        // Pushes RedactingProcessor onto the DEFAULT log channel only.
        // Named channels (Log::channel('slack'), etc.) need manual wiring.
        // See docs/audit-log.md § "Redactor: Monolog channel wiring".
        try {
            $logger = $this->app->make('log')->driver();
        } catch (\Throwable) {
            return;
        }

        if (! method_exists($logger, 'getLogger')) {
            return;
        }

        $monolog = $logger->getLogger();
        if (! $monolog instanceof \Monolog\Logger) {
            return;
        }

        $redactor = $this->app->make(\Bloxy\Core\Observability\Redactor::class);
        $monolog->pushProcessor(new \Bloxy\Core\Observability\RedactingProcessor($redactor));
    }

    private function wireSentry(): void
    {
        if (self::$sentryRedactorWired) {
            return;
        }

        // Sentry's options are typically configured via config/sentry.php
        // before its service provider boots. We can't override after-the-fact
        // without disrupting Sentry's own setup, so we install a hub
        // initialization listener that wraps any existing before_send.
        try {
            $hub = \Sentry\State\Hub::getCurrent();
            $client = $hub->getClient();
            if ($client === null) {
                return;
            }

            $options = $client->getOptions();
            $existing = $options->getBeforeSendCallback();
            $redactor = $this->app->make(\Bloxy\Core\Observability\Redactor::class);
            $bloxyCallback = (new \Bloxy\Core\Observability\SentryRedactor($redactor))->beforeSend();

            $options->setBeforeSendCallback(function (object $event) use ($existing, $bloxyCallback): ?object {
                // Run any pre-existing before_send callback FIRST so the event
                // reaches its final shape (including app-level enrichment like
                // Auth::user() context). Then redact as the last gate before
                // the event ships, so any PII added by enrichment is caught.
                if ($existing !== null) {
                    $event = $existing($event);
                    if ($event === null) {
                        return null;
                    }
                }
                return $bloxyCallback($event);
            });
            self::$sentryRedactorWired = true;
        } catch (\Throwable) {
            // Sentry SDK not installed / not configured; skip silently.
        }
    }
}
