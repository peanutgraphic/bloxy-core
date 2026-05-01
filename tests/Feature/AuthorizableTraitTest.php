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

    $u->assignRole('heir');

    $assignment = RoleAssignment::query()->first();
    expect($assignment)->not->toBeNull();
    expect($assignment->user_type)->toBe('test-user');
    expect($assignment->user_id)->toBe((string) $u->id);
    expect($assignment->role_id)->toBe($this->heir->id);
});

it('assignRole on a specific resource scopes the assignment', function () {
    $u = TestUser::create(['name' => 'Alice']);

    $u->assignRole('heir', resourceType: 'App\\Document', resourceId: '7');

    $assignment = RoleAssignment::query()->first();
    expect($assignment->resource_type)->toBe('App\\Document');
    expect($assignment->resource_id)->toBe('7');
});

it('hasRole returns true after assignment', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $u->assignRole('heir');

    expect($u->hasRole('heir'))->toBeTrue();
    expect($u->hasRole('executor'))->toBeFalse();
});

it('canBloxy delegates to the resolver', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $u->assignRole('heir');

    expect($u->canBloxy('documents.read'))->toBeTrue();
    expect($u->canBloxy('documents.write'))->toBeFalse();
});

it('revokeRole removes the assignment', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $u->assignRole('heir');

    $u->revokeRole('heir');

    expect(RoleAssignment::query()->count())->toBe(0);
    expect($u->hasRole('heir'))->toBeFalse();
});

it('throws when assigning a role that does not exist', function () {
    $u = TestUser::create(['name' => 'Alice']);

    expect(fn () => $u->assignRole('nonexistent'))
        ->toThrow(InvalidArgumentException::class);
});

it('canBloxy accepts an Eloquent model directly', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $resource = TestUser::create(['name' => 'ResourceModel']);
    $u->assignRole('heir', $resource);

    expect($u->canBloxy('documents.read', $resource))->toBeTrue();
    expect($u->canBloxy('documents.write', $resource))->toBeFalse();
});

it('assignRole accepts an Eloquent model directly', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $resource = TestUser::create(['name' => 'ResourceModel']);

    $u->assignRole('heir', $resource);

    $assignment = \Bloxy\Core\Rbac\RoleAssignment::query()->first();
    expect($assignment->resource_type)->toBe($resource->getMorphClass());
    expect($assignment->resource_id)->toBe((string) $resource->getKey());
});

it('revokeRole accepts an Eloquent model directly', function () {
    $u = TestUser::create(['name' => 'Alice']);
    $resource = TestUser::create(['name' => 'ResourceModel']);
    $u->assignRole('heir', $resource);

    $u->revokeRole('heir', $resource);

    expect(\Bloxy\Core\Rbac\RoleAssignment::query()->count())->toBe(0);
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
