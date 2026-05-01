<?php

declare(strict_types=1);

namespace Bloxy\Core\Identity;

use Bloxy\Core\Rbac\BloxyAccessResolver;
use Bloxy\Core\Rbac\Role;
use Bloxy\Core\Rbac\RoleAssignment;
use InvalidArgumentException;

/**
 * Drop-in trait that gives a User model the BLOXY identity API.
 *
 * Methods are prefixed `canBloxy` (not `can`) to avoid colliding with
 * Laravel's Authenticatable::can().
 */
trait Authorizable
{
    public function assignRole(
        string $roleName,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $activationPredicate = null,
    ): RoleAssignment {
        $role = Role::query()->where('name', $roleName)->first();
        if ($role === null) {
            throw new InvalidArgumentException("Role [{$roleName}] does not exist.");
        }

        return RoleAssignment::firstOrCreate(
            [
                'user_type' => $this->getMorphClass(),
                'user_id' => (string) $this->getKey(),
                'role_id' => $role->id,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
            ],
            [
                'activation_predicate' => $activationPredicate,
            ]
        );
    }

    public function revokeRole(
        string $roleName,
        ?string $resourceType = null,
        ?string $resourceId = null,
    ): int {
        $role = Role::query()->where('name', $roleName)->first();
        if ($role === null) {
            return 0;
        }

        return RoleAssignment::query()
            ->where('user_type', $this->getMorphClass())
            ->where('user_id', (string) $this->getKey())
            ->where('role_id', $role->id)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->delete();
    }

    public function hasRole(string $roleName): bool
    {
        return $this->resolver()->hasRole(
            $this->getMorphClass(),
            (string) $this->getKey(),
            $roleName,
        );
    }

    public function canBloxy(
        string $permission,
        ?string $resourceType = null,
        ?string $resourceId = null,
    ): bool {
        return $this->resolver()->check(
            $this->getMorphClass(),
            (string) $this->getKey(),
            $permission,
            $resourceType,
            $resourceId,
        );
    }

    private function resolver(): BloxyAccessResolver
    {
        if (function_exists('app')) {
            return app(BloxyAccessResolver::class);
        }
        return new BloxyAccessResolver();
    }
}
