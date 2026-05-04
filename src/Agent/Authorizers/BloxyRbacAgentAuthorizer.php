<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Authorizers;

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentAuthorizer;
use Bloxy\Core\Rbac\BloxyAccessResolver;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Default authorizer (M2 Sub-plan B-0).
 *
 * Hard-requires permission 'agent.invoke.{$agent->name()}' on the
 * authenticated user. Anonymous requests are denied. The check is global
 * (no resource scope) — per-agent permissions are the unit of grant.
 */
final class BloxyRbacAgentAuthorizer implements AgentAuthorizer
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly BloxyAccessResolver $resolver,
    ) {}

    public function mayInvoke(Agent $agent, array $params): bool
    {
        $user = $this->auth->guard()->user();
        if ($user === null) {
            return false;
        }

        if (! method_exists($user, 'getMorphClass')) {
            throw new \LogicException(
                'BloxyRbacAgentAuthorizer requires the authenticated user to expose getMorphClass(); '
                . 'either use an Eloquent-backed Authenticatable or bind a custom AgentAuthorizer.',
            );
        }

        $identifier = $user->getAuthIdentifier();
        if ($identifier === null || $identifier === '') {
            throw new \LogicException(
                'BloxyRbacAgentAuthorizer requires a non-empty getAuthIdentifier(); got null or empty string.',
            );
        }

        return $this->resolver->check(
            userType: $user->getMorphClass(),
            userId: (string) $identifier,
            permissionName: 'agent.invoke.' . $agent->name(),
        );
    }
}
