<?php

declare(strict_types=1);

namespace Bloxy\Core\Audit\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;

class AuditCoverageCommand extends Command
{
    protected $signature = 'bloxy:audit-coverage';

    protected $description = 'Report state-changing routes (POST/PUT/PATCH/DELETE) NOT under the bloxy.audit middleware. Exits non-zero if any are found.';

    public function handle(Router $router): int
    {
        $stateChanging = ['POST', 'PUT', 'PATCH', 'DELETE'];
        $alias = (string) config('bloxy.audit.middleware_alias', 'bloxy.audit');
        $excludes = (array) config('bloxy.audit.coverage_excludes', []);

        $uncovered = [];

        foreach ($router->getRoutes() as $route) {
            $methods = array_intersect($route->methods(), $stateChanging);
            if ($methods === []) {
                continue;
            }

            $uri = $route->uri();
            if ($this->isExcluded($uri, $excludes)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            // gatherMiddleware returns aliases AND fully-qualified class names; check both.
            $hasAudit = in_array($alias, $middleware, true)
                || in_array(\Bloxy\Core\Audit\AuditMiddleware::class, $middleware, true);

            if (! $hasAudit) {
                $uncovered[] = sprintf(
                    '%s %s',
                    implode('|', $methods),
                    $uri,
                );
            }
        }

        if ($uncovered === []) {
            $this->info('OK — every state-changing route is covered by bloxy.audit.');
            return self::SUCCESS;
        }

        $this->error(sprintf('Found %d uncovered state-changing route(s):', count($uncovered)));
        foreach ($uncovered as $line) {
            $this->line('  - ' . $line);
        }
        return self::FAILURE;
    }

    /**
     * @param array<int, string> $excludes
     */
    private function isExcluded(string $uri, array $excludes): bool
    {
        foreach ($excludes as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }
            if (@preg_match($pattern, $uri) === 1) {
                return true;
            }
        }
        return false;
    }
}
