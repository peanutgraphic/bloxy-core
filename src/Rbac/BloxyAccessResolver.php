<?php

declare(strict_types=1);

namespace Bloxy\Core\Rbac;

/**
 * Answers "does identity X have permission P (optionally on resource R)?"
 *
 * M1.3 semantics:
 * - An assignment is active if `activation_predicate` IS NULL.
 * - Non-null predicates are reserved for M1.4+; until then they read as inactive.
 * - A global assignment (resource_type/resource_id NULL) grants permission
 *   regardless of any resource argument.
 * - A resource-scoped assignment grants permission only on its exact
 *   (resource_type, resource_id) tuple.
 */
class BloxyAccessResolver
{
    public function check(
        string $userType,
        string $userId,
        string $permissionName,
        ?string $resourceType = null,
        ?string $resourceId = null,
    ): bool {
        $assignments = $this->activeAssignmentsFor($userType, $userId);

        if ($assignments->isEmpty()) {
            return false;
        }

        $roleIds = $assignments->pluck('role_id')->unique()->all();

        $rolesGrantingPermission = Permission::query()
            ->where('name', $permissionName)
            ->whereHas('roles', function ($q) use ($roleIds) {
                $q->whereIn('bloxy_roles.id', $roleIds);
            })
            ->with(['roles' => function ($q) use ($roleIds) {
                $q->whereIn('bloxy_roles.id', $roleIds);
            }])
            ->first();

        if ($rolesGrantingPermission === null) {
            return false;
        }

        $grantingRoleIds = $rolesGrantingPermission->roles->pluck('id')->all();

        foreach ($assignments as $assignment) {
            if (! in_array($assignment->role_id, $grantingRoleIds, true)) {
                continue;
            }

            if ($assignment->resource_type === null && $assignment->resource_id === null) {
                return true;
            }

            if (
                $resourceType !== null
                && $resourceId !== null
                && $assignment->resource_type === $resourceType
                && $assignment->resource_id === $resourceId
            ) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string $userType, string $userId, string $roleName): bool
    {
        $role = Role::query()->where('name', $roleName)->first();
        if ($role === null) {
            return false;
        }

        return $this->activeAssignmentsFor($userType, $userId)
            ->contains(fn (RoleAssignment $a) => $a->role_id === $role->id);
    }

    private function activeAssignmentsFor(string $userType, string $userId)
    {
        return RoleAssignment::query()
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->whereNull('activation_predicate')
            ->get();
    }
}
