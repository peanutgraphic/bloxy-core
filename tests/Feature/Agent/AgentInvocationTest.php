<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\AgentAuthorizer;
use Bloxy\Core\Agent\AgentRegistry;
use Bloxy\Core\Agent\Authorizers\AllowAllAgentAuthorizer;
use Bloxy\Core\Agent\Concerns\HasDefaultVisibility;
use Bloxy\Core\Agent\Exceptions\AgentAuthorizationDeniedException;
use Bloxy\Core\Agent\Exceptions\AgentInvocationFailedException;
use Bloxy\Core\Agent\Exceptions\AgentNotFoundException;
use Bloxy\Core\Audit\AuditLog;

beforeEach(function () {
    // Default to AllowAll for invocation tests; authz is exercised separately in AgentAuthorizerTest.
    app()->bind(AgentAuthorizer::class, AllowAllAgentAuthorizer::class);
});

class InvokeTestAgent implements Agent {
    use HasDefaultVisibility;
    public function __construct(
        private string $n,
        private \Closure $body,
    ) {}
    public function name(): string { return $this->n; }
    public function description(): string { return 'invoke test'; }
    public function invoke(array $params): array { return ($this->body)($params); }
}

it('invokes a registered agent and returns its result', function () {
    $registry = app(AgentRegistry::class);
    $registry->register(new InvokeTestAgent('demo.echo', fn ($p) => ['echoed' => $p]));
    expect($registry->invoke('demo.echo', ['x' => 1]))->toBe(['echoed' => ['x' => 1]]);
});

it('throws AgentNotFoundException for unknown names', function () {
    $registry = app(AgentRegistry::class);
    expect(fn () => $registry->invoke('does-not-exist', []))
        ->toThrow(AgentNotFoundException::class);
});

it('throws AgentAuthorizationDeniedException when the authorizer denies', function () {
    app()->bind(AgentAuthorizer::class, fn () => new class implements AgentAuthorizer {
        public function mayInvoke(Agent $a, array $p): bool { return false; }
    });

    $registry = app(AgentRegistry::class);
    $registry->register(new InvokeTestAgent('demo.locked', fn ($p) => $p));

    expect(fn () => $registry->invoke('demo.locked', []))
        ->toThrow(AgentAuthorizationDeniedException::class);
});

it('wraps agent-thrown exceptions in AgentInvocationFailedException', function () {
    $registry = app(AgentRegistry::class);
    $registry->register(new InvokeTestAgent('demo.boom', function () {
        throw new RuntimeException('kaboom');
    }));

    try {
        $registry->invoke('demo.boom', []);
        $this->fail('expected exception');
    } catch (AgentInvocationFailedException $e) {
        expect($e->getPrevious())->toBeInstanceOf(RuntimeException::class);
        expect($e->getPrevious()->getMessage())->toBe('kaboom');
    }
});

it('emits AGENT_INVOKE audit row on success with redacted params + result_sha256', function () {
    $registry = app(AgentRegistry::class);
    $registry->register(new InvokeTestAgent('demo.audited', fn ($p) => ['done' => true]));
    $registry->invoke('demo.audited', ['secret' => 'shh']);

    $row = AuditLog::query()->where('action', 'AGENT_INVOKE')->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->subject_type)->toBe(\Bloxy\Core\Agent\AgentRegistry::class);
    expect($row->subject_id)->toBe('demo.audited');
    expect($row->meta['result_sha256'])->toBe(hash('sha256', json_encode(['done' => true], JSON_THROW_ON_ERROR)));
});

it('emits AGENT_INVOKE_DENIED audit row when the authorizer denies', function () {
    app()->bind(AgentAuthorizer::class, fn () => new class implements AgentAuthorizer {
        public function mayInvoke(Agent $a, array $p): bool { return false; }
    });

    $registry = app(AgentRegistry::class);
    $registry->register(new InvokeTestAgent('demo.denied', fn ($p) => $p));

    try { $registry->invoke('demo.denied', []); } catch (AgentAuthorizationDeniedException $e) {}

    $row = AuditLog::query()->where('action', 'AGENT_INVOKE_DENIED')->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->subject_id)->toBe('demo.denied');
});

it('emits AGENT_INVOKE_FAILED audit row on agent throw', function () {
    $registry = app(AgentRegistry::class);
    $registry->register(new InvokeTestAgent('demo.failed', function () {
        throw new RuntimeException('nope');
    }));

    try { $registry->invoke('demo.failed', []); } catch (AgentInvocationFailedException $e) {}

    $row = AuditLog::query()->where('action', 'AGENT_INVOKE_FAILED')->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->subject_id)->toBe('demo.failed');
    expect($row->meta['exception'])->toBe(RuntimeException::class);
});

it('returns the original AgentInvocationFailedException even if audit emission throws', function () {
    // Inject a failure into the existing chain-signing pathway: when
    // bloxy.audit.signed_chain is on AND the signer throws, AuditLog::boot()
    // re-throws from the saving hook. Portable across SQLite + Postgres
    // (unlike Schema::rename, which leaves Postgres in an aborted-transaction
    // state for the rest of the test).
    config()->set('bloxy.audit.signed_chain', true);
    app()->bind(\Bloxy\Core\Audit\ChainSigner::class, function () {
        return new class extends \Bloxy\Core\Audit\ChainSigner {
            public function sign(\Bloxy\Core\Audit\AuditLog $row): void
            {
                throw new \RuntimeException('forced audit-emit failure for test');
            }
        };
    });

    $registry = app(AgentRegistry::class);
    $registry->register(new InvokeTestAgent('demo.failwithbrokenaudit', function () {
        throw new RuntimeException('the original cause');
    }));

    try {
        $registry->invoke('demo.failwithbrokenaudit', []);
        test()->fail('expected AgentInvocationFailedException');
    } catch (AgentInvocationFailedException $e) {
        expect($e->getPrevious())->toBeInstanceOf(RuntimeException::class);
        expect($e->getPrevious()->getMessage())->toBe('the original cause');
    }
});

it('falls back to result_sha256=null + result_serialization_error when result is non-serializable', function () {
    $registry = app(AgentRegistry::class);
    $registry->register(new InvokeTestAgent('demo.unserializable', function () {
        // NaN is not representable in JSON and triggers JsonException with JSON_THROW_ON_ERROR.
        return ['bad' => NAN];
    }));

    $result = $registry->invoke('demo.unserializable', []);
    expect(is_nan($result['bad']))->toBeTrue();

    $row = AuditLog::query()->where('action', 'AGENT_INVOKE')->where('subject_id', 'demo.unserializable')->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->meta['result_sha256'])->toBeNull();
    expect($row->meta['result_serialization_error'])->toBe(\JsonException::class);
});

it('records actor_type=null when the authenticated user has no getMorphClass()', function () {
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

    $registry = app(AgentRegistry::class);
    $registry->register(new InvokeTestAgent('demo.no-morph-actor', fn ($p) => ['ok' => true]));
    $registry->invoke('demo.no-morph-actor', []);

    $row = AuditLog::query()->where('action', 'AGENT_INVOKE')->where('subject_id', 'demo.no-morph-actor')->latest('id')->first();
    expect($row->actor_type)->toBeNull();
    expect($row->actor_id)->toBe('no-morph-1');
});
