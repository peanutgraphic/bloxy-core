<?php

declare(strict_types=1);

namespace Bloxy\Core\Testing;

use Bloxy\Core\Audit\AuditMiddleware;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Pest/PHPUnit assertions for audit-coverage. Use in test cases that extend
 * Laravel's Testbench / Foundation TestCase — the trait depends on
 * $this->app, $this->artisan(), and config().
 *
 * @phpstan-require-extends \Illuminate\Foundation\Testing\TestCase
 */
trait AssertsAuditCoverage
{
    public function assertRouteHasAudit(string $routeName): void
    {
        $route = $this->app['router']->getRoutes()->getByName($routeName);
        Assert::assertNotNull($route, "Route [{$routeName}] does not exist.");

        $alias = (string) config('bloxy.audit.middleware_alias', 'bloxy.audit');
        $middleware = $route->gatherMiddleware();

        $hasAudit = in_array($alias, $middleware, true)
            || in_array(AuditMiddleware::class, $middleware, true);

        Assert::assertTrue(
            $hasAudit,
            "Route [{$routeName}] is missing bloxy.audit middleware."
        );
    }

    public function assertNoUncoveredStateChangingRoutes(): void
    {
        if (! method_exists($this, 'artisan')) {
            throw new RuntimeException(
                'AssertsAuditCoverage::assertNoUncoveredStateChangingRoutes() requires the test class '
                . 'to extend Illuminate\\Foundation\\Testing\\TestCase (or Orchestra Testbench TestCase). '
                . 'The current class lacks the artisan() method.'
            );
        }

        $exit = $this->artisan('bloxy:audit-coverage')->run();
        Assert::assertSame(
            0,
            $exit,
            'bloxy:audit-coverage reports uncovered state-changing routes (run the command for details).'
        );
    }
}
