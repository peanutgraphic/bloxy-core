<?php

declare(strict_types=1);

namespace Bloxy\Core\Identity;

use Bloxy\Core\Rbac\BloxyAccessResolver;
use Bloxy\Core\Rbac\Role;
use Bloxy\Core\Rbac\RoleAssignment;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Drop-in trait that gives a User model the BLOXY identity API.
 *
 * Methods are prefixed `canBloxy` (not `can`) to avoid colliding with
 * Laravel's Authenticatable::can().
 *
 * Each resource-scoped method accepts EITHER an Eloquent Model directly
 * (preferred) OR a (resourceType, resourceId) string pair (for non-Eloquent
 * identities or when the model isn't loaded). When a Model is passed, the
 * resourceId arg is ignored — the trait extracts class + key from it.
 */
trait Authorizable
{
    public function assignRole(
        string $roleName,
        Model|string|null $resourceType = null,
        ?string $resourceId = null,
        ?array $activationPredicate = null,
    ): RoleAssignment {
        $role = Role::query()->where('name', $roleName)->first();
        if ($role === null) {
            throw new InvalidArgumentException("Role [{$roleName}] does not exist.");
        }

        [$resolvedType, $resolvedId] = $this->resolveResource($resourceType, $resourceId);

        return RoleAssignment::firstOrCreate(
            [
                'user_type' => $this->getMorphClass(),
                'user_id' => (string) $this->getKey(),
                'role_id' => $role->id,
                'resource_type' => $resolvedType,
                'resource_id' => $resolvedId,
            ],
            [
                'activation_predicate' => $activationPredicate,
            ]
        );
    }

    public function revokeRole(
        string $roleName,
        Model|string|null $resourceType = null,
        ?string $resourceId = null,
    ): int {
        $role = Role::query()->where('name', $roleName)->first();
        if ($role === null) {
            return 0;
        }

        [$resolvedType, $resolvedId] = $this->resolveResource($resourceType, $resourceId);

        return RoleAssignment::query()
            ->where('user_type', $this->getMorphClass())
            ->where('user_id', (string) $this->getKey())
            ->where('role_id', $role->id)
            ->where('resource_type', $resolvedType)
            ->where('resource_id', $resolvedId)
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
        Model|string|null $resourceType = null,
        ?string $resourceId = null,
    ): bool {
        [$resolvedType, $resolvedId] = $this->resolveResource($resourceType, $resourceId);

        return $this->resolver()->check(
            $this->getMorphClass(),
            (string) $this->getKey(),
            $permission,
            $resolvedType,
            $resolvedId,
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveResource(Model|string|null $resourceType, ?string $resourceId): array
    {
        if ($resourceType instanceof Model) {
            return [
                $resourceType->getMorphClass(),
                (string) $resourceType->getKey(),
            ];
        }
        return [$resourceType, $resourceId];
    }

    private function resolver(): BloxyAccessResolver
    {
        if (function_exists('app')) {
            return app(BloxyAccessResolver::class);
        }
        return new BloxyAccessResolver();
    }
}
