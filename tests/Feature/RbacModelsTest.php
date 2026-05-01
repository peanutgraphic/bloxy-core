<?php

declare(strict_types=1);

use Bloxy\Core\Rbac\Permission;
use Bloxy\Core\Rbac\Role;
use Bloxy\Core\Rbac\RoleAssignment;

it('creates a role with name and description', function () {
    $role = Role::create(['name' => 'heir', 'description' => 'Designated heir']);

    expect($role->id)->toBeInt();
    expect($role->name)->toBe('heir');
    expect($role->description)->toBe('Designated heir');
});

it('creates a permission and attaches it to a role via the pivot', function () {
    $role = Role::create(['name' => 'heir']);
    $permission = Permission::create(['name' => 'documents.read']);

    $role->permissions()->attach($permission->id);

    expect($role->fresh()->permissions)->toHaveCount(1);
    expect($role->fresh()->permissions->first()->name)->toBe('documents.read');
});

it('creates a global role assignment', function () {
    $role = Role::create(['name' => 'executor']);

    $assignment = RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $role->id,
    ]);

    expect($assignment->resource_type)->toBeNull();
    expect($assignment->resource_id)->toBeNull();
    expect($assignment->activation_predicate)->toBeNull();
});

it('creates a resource-scoped role assignment', function () {
    $role = Role::create(['name' => 'reader']);

    $assignment = RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $role->id,
        'resource_type' => 'App\\Models\\Document',
        'resource_id' => '7',
    ]);

    expect($assignment->resource_type)->toBe('App\\Models\\Document');
    expect($assignment->resource_id)->toBe('7');
});

it('stores the activation_predicate as JSON', function () {
    $role = Role::create(['name' => 'heir']);

    $assignment = RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $role->id,
        'activation_predicate' => ['type' => 'death-verified', 'subject_user_id' => '1'],
    ]);

    $reloaded = RoleAssignment::find($assignment->id);
    expect($reloaded->activation_predicate)->toBe([
        'type' => 'death-verified',
        'subject_user_id' => '1',
    ]);
});

it('enforces the unique assignment constraint', function () {
    $role = Role::create(['name' => 'reader']);

    RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $role->id,
    ]);

    expect(fn () => RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $role->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
