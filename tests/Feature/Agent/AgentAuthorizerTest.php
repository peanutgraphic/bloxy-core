<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\Authorizers\AllowAllAgentAuthorizer;
use Bloxy\Core\Agent\Authorizers\BloxyRbacAgentAuthorizer;
use Bloxy\Core\Agent\Concerns\HasDefaultVisibility;
use Bloxy\Core\Rbac\Permission;
use Bloxy\Core\Rbac\Role;
use Bloxy\Core\Rbac\RoleAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('authz_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('authz_test_users');
});

it('AllowAllAgentAuthorizer permits everything', function () {
    $authz = new AllowAllAgentAuthorizer();
    expect($authz->mayInvoke(new AuthzTestAgent('demo.x'), []))->toBeTrue();
});

it('BloxyRbacAgentAuthorizer denies when no user is authenticated', function () {
    $authz = app(BloxyRbacAgentAuthorizer::class);
    expect($authz->mayInvoke(new AuthzTestAgent('demo.locked'), []))->toBeFalse();
});

it('BloxyRbacAgentAuthorizer denies when the user lacks agent.invoke.{name}', function () {
    $u = AuthzTestUser::create(['name' => 'Alice']);
    Auth::login($u);

    $authz = app(BloxyRbacAgentAuthorizer::class);
    expect($authz->mayInvoke(new AuthzTestAgent('demo.locked'), []))->toBeFalse();
});

it('BloxyRbacAgentAuthorizer permits when the user has agent.invoke.{name}', function () {
    $role = Role::create(['name' => 'agent-invoker']);
    $perm = Permission::create(['name' => 'agent.invoke.demo.allowed']);
    $role->permissions()->attach($perm->id);

    $u = AuthzTestUser::create(['name' => 'Alice']);
    RoleAssignment::create([
        'user_type' => $u->getMorphClass(),
        'user_id' => (string) $u->getKey(),
        'role_id' => $role->id,
    ]);

    Auth::login($u);

    $authz = app(BloxyRbacAgentAuthorizer::class);
    expect($authz->mayInvoke(new AuthzTestAgent('demo.allowed'), []))->toBeTrue();
});

it('BloxyRbacAgentAuthorizer throws when the authenticated user has no getMorphClass()', function () {
    // A bare Authenticatable that does NOT expose getMorphClass().
    $user = new class implements \Illuminate\Contracts\Auth\Authenticatable {
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return 'no-morph-1'; }
        public function getAuthPasswordName() { return 'password'; }
        public function getAuthPassword() { return ''; }
        public function getRememberToken() { return null; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return 'remember_token'; }
    };

    \Illuminate\Support\Facades\Auth::setUser($user);

    $authz = app(\Bloxy\Core\Agent\Authorizers\BloxyRbacAgentAuthorizer::class);

    expect(fn () => $authz->mayInvoke(new AuthzTestAgent('demo.no-morph'), []))
        ->toThrow(\LogicException::class);
});

class AuthzTestAgent implements Agent
{
    use HasDefaultVisibility;

    public function __construct(private readonly string $name) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return 'Test agent for AgentAuthorizer';
    }

    public function invoke(array $params): array
    {
        return $params;
    }
}

class AuthzTestUser extends AuthUser
{
    protected $table = 'authz_test_users';
    protected $guarded = [];

    public function getMorphClass(): string
    {
        return 'authz-test-user';
    }
}
