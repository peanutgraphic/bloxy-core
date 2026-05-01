<?php

declare(strict_types=1);

use Bloxy\Core\Rbac\BloxyAccessResolver;
use Bloxy\Core\Rbac\Permission;
use Bloxy\Core\Rbac\Role;
use Bloxy\Core\Rbac\RoleAssignment;

beforeEach(function () {
    $this->resolver = new BloxyAccessResolver();

    $this->heir = Role::create(['name' => 'heir']);
    $this->readPerm = Permission::create(['name' => 'documents.read']);
    $this->writePerm = Permission::create(['name' => 'documents.write']);
    $this->heir->permissions()->attach($this->readPerm->id);
});

it('grants permission via a global role assignment', function () {
    RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $this->heir->id,
    ]);

    expect($this->resolver->check('user', '42', 'documents.read'))->toBeTrue();
    expect($this->resolver->check('user', '42', 'documents.write'))->toBeFalse();
});

it('denies permission for a different user', function () {
    RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $this->heir->id,
    ]);

    expect($this->resolver->check('user', '99', 'documents.read'))->toBeFalse();
});

it('grants resource-scoped permission only on the matching resource', function () {
    RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $this->heir->id,
        'resource_type' => 'App\\Document',
        'resource_id' => '7',
    ]);

    expect($this->resolver->check('user', '42', 'documents.read', 'App\\Document', '7'))->toBeTrue();
    expect($this->resolver->check('user', '42', 'documents.read', 'App\\Document', '8'))->toBeFalse();
});

it('global assignment also grants on a specific resource', function () {
    RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $this->heir->id,
    ]);

    expect($this->resolver->check('user', '42', 'documents.read', 'App\\Document', '7'))->toBeTrue();
});

it('a resource-scoped assignment does NOT grant globally', function () {
    RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $this->heir->id,
        'resource_type' => 'App\\Document',
        'resource_id' => '7',
    ]);

    expect($this->resolver->check('user', '42', 'documents.read'))->toBeFalse();
});

it('an assignment with a non-null activation_predicate is treated as inactive', function () {
    RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $this->heir->id,
        'activation_predicate' => ['type' => 'death-verified', 'subject_user_id' => '1'],
    ]);

    expect($this->resolver->check('user', '42', 'documents.read'))->toBeFalse();
});

it('hasRole returns true only when the user has at least one active assignment for the role', function () {
    RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $this->heir->id,
    ]);

    expect($this->resolver->hasRole('user', '42', 'heir'))->toBeTrue();
    expect($this->resolver->hasRole('user', '42', 'executor'))->toBeFalse();
    expect($this->resolver->hasRole('user', '99', 'heir'))->toBeFalse();
});

it('hasRole returns false when only assignment is gated by a predicate', function () {
    RoleAssignment::create([
        'user_type' => 'user',
        'user_id' => '42',
        'role_id' => $this->heir->id,
        'activation_predicate' => ['type' => 'death-verified'],
    ]);

    expect($this->resolver->hasRole('user', '42', 'heir'))->toBeFalse();
});
