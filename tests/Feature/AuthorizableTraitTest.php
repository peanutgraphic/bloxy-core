<?php

declare(strict_types=1);

use Bloxy\Core\Identity\Authorizable;
use Bloxy\Core\Rbac\Permission;
use Bloxy\Core\Rbac\Role;
use Bloxy\Core\Rbac\RoleAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    $this->heir = Role::create(['name' => 'heir']);
    $this->readPerm = Permission::create(['name' => 'documents.read']);
    $this->heir->permissions()->attach($this->readPerm->id);
});

afterEach(function () {
    Schema::dropIfExists('test_users');
});

it('assignRole creates a role assignment for the user', function () {
    $u = TestUser::create(['name' => 'Alice']);

    $u->bloxyAssignRole('heir');

    $assignment = RoleAssignment::query()->first();
    expect($assignment)->not->toBeNull();
    expect($assignment->user_type)->toBe('test-user');
    expect($assignment->user_id)->toBe((string) $u->id);
    expect($assignment->role_id)->toBe($this->heir->id);
});

it('assignRole on a specific resource scopes the assignment', function () {
    $u = TestUser::create(['name' => 'Alice']);

    $u->bloxyAssignRole('heir', resourceType: 'App\\Document', resourceId: '7');

    $assignment = RoleAssignment::query()->first();
    expect($assignment->resource_type)->toBe('App\\Document');
    expect($assignment->resource_id)->toBe('7');
});

it('hasRole returns true after assignment', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $u->bloxyAssignRole('heir');

    expect($u->bloxyHasRole('heir'))->toBeTrue();
    expect($u->bloxyHasRole('executor'))->toBeFalse();
});

it('canBloxy delegates to the resolver', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $u->bloxyAssignRole('heir');

    expect($u->bloxyCan('documents.read'))->toBeTrue();
    expect($u->bloxyCan('documents.write'))->toBeFalse();
});

it('revokeRole removes the assignment', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $u->bloxyAssignRole('heir');

    $u->bloxyRevokeRole('heir');

    expect(RoleAssignment::query()->count())->toBe(0);
    expect($u->bloxyHasRole('heir'))->toBeFalse();
});

it('throws when assigning a role that does not exist', function () {
    $u = TestUser::create(['name' => 'Alice']);

    expect(fn () => $u->bloxyAssignRole('nonexistent'))
        ->toThrow(InvalidArgumentException::class);
});

it('canBloxy accepts an Eloquent model directly', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $resource = TestUser::create(['name' => 'ResourceModel']);
    $u->bloxyAssignRole('heir', $resource);

    expect($u->bloxyCan('documents.read', $resource))->toBeTrue();
    expect($u->bloxyCan('documents.write', $resource))->toBeFalse();
});

it('assignRole accepts an Eloquent model directly', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $resource = TestUser::create(['name' => 'ResourceModel']);

    $u->bloxyAssignRole('heir', $resource);

    $assignment = \Bloxy\Core\Rbac\RoleAssignment::query()->first();
    expect($assignment->resource_type)->toBe($resource->getMorphClass());
    expect($assignment->resource_id)->toBe((string) $resource->getKey());
});

it('revokeRole accepts an Eloquent model directly', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $resource = TestUser::create(['name' => 'ResourceModel']);
    $u->bloxyAssignRole('heir', $resource);

    $u->bloxyRevokeRole('heir', $resource);

    expect(\Bloxy\Core\Rbac\RoleAssignment::query()->count())->toBe(0);
});

it('bloxyAssignRole creates a global role assignment', function () {
    $u = TestUser::create(['name' => 'Alice']);

    $assignment = $u->bloxyAssignRole('heir');

    expect($assignment)->toBeInstanceOf(RoleAssignment::class);
    expect($assignment->resource_type)->toBeNull();
    expect($assignment->resource_id)->toBeNull();
});

it('bloxyHasRole returns false before assignment, true after', function () {
    $u = TestUser::create(['name' => 'Alice']);

    expect($u->bloxyHasRole('heir'))->toBeFalse();

    $u->bloxyAssignRole('heir');

    expect($u->bloxyHasRole('heir'))->toBeTrue();
});

it('bloxyCan returns true for a permission granted via role', function () {
    $role = Role::create(['name' => 'editor']);
    $perm = Permission::create(['name' => 'documents.write']);
    $role->permissions()->attach($perm->id);

    $u = TestUser::create(['name' => 'Alice']);
    $u->bloxyAssignRole('editor');

    expect($u->bloxyCan('documents.write'))->toBeTrue();
});

it('bloxyRevokeRole removes the assignment', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $u->bloxyAssignRole('heir');

    $deleted = $u->bloxyRevokeRole('heir');

    expect($deleted)->toBe(1);
    expect($u->bloxyHasRole('heir'))->toBeFalse();
});

it('bloxyRevokeRole returns 0 when the role does not exist', function () {
    $u = TestUser::create(['name' => 'Alice']);

    $deleted = $u->bloxyRevokeRole('role-that-does-not-exist');

    expect($deleted)->toBe(0);
});

class TestUser extends Model
{
    use Authorizable;

    protected $table = 'test_users';
    protected $guarded = [];

    public function getMorphClass(): string
    {
        return 'test-user';
    }
}
