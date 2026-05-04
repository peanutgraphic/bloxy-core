<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Http;

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentAuthorizer;
use Bloxy\Core\Agent\AgentRegistry;
use Closure;
use Illuminate\Http\Request;

/**
 * Share the per-user, per-surface agent list with Inertia.
 *
 * Apps register this middleware in their HTTP kernel (typically alongside
 * HandleInertiaRequests). On every request, it reads the registry, runs
 * each agent through the bound AgentAuthorizer (so only agents the current
 * user can invoke are exposed), groups them by Agent::visibleIn() surface,
 * and shares them as Inertia::share('agents', ...).
 *
 * Inertia is a soft dep. If \Inertia\Inertia is not installed, the
 * middleware is a no-op — the request flows through untouched.
 */
final class ShareAgentsWithInertia
{
    public function __construct(
        private readonly AgentRegistry $registry,
        private readonly AgentAuthorizer $authorizer,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (! class_exists(\Inertia\Inertia::class)) {
            return $next($request);
        }

        \Inertia\Inertia::share('agents', function () {
            $cockpit = [];
            $portal = [];

            foreach ($this->registry->all() as $agent) {
                if (! $this->authorizer->mayInvoke($agent, [])) {
                    continue;
                }
                $ref = ['name' => $agent->name(), 'description' => $agent->description()];
                $surfaces = $agent->visibleIn();
                if (in_array('cockpit', $surfaces, true)) {
                    $cockpit[] = $ref;
                }
                if (in_array('portal', $surfaces, true)) {
                    $portal[] = $ref;
                }
            }

            return ['cockpit' => $cockpit, 'portal' => $portal];
        });

        return $next($request);
    }
}
