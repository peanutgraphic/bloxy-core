<?php

declare(strict_types=1);

use Bloxy\Core\Audit\AuditLog;

beforeEach(function (): void {
    config()->set('bloxy.audit.signed_chain', true);
    config()->set('bloxy.audit.hmac_keys', [1 => str_repeat('a', 64)]);
    config()->set('bloxy.audit.active_signing_key_id', 1);
});

it('refuses to run without --reason', function () {
    $this->artisan('bloxy:audit-anchor')
        ->expectsOutputToContain('--reason is required')
        ->assertExitCode(1);
});

it('writes a chain_anchor audit_log row when --reason is provided', function () {
    $this->artisan('bloxy:audit-anchor', ['--reason' => 'enabling chain'])
        ->assertExitCode(0);

    $anchor = AuditLog::query()->where('action', 'chain_anchor')->first();
    expect($anchor)->not->toBeNull();
    expect($anchor->meta)->toBe(['reason' => 'enabling chain']);
});

it('produces an anchor that the verifier treats as a legitimate break', function () {
    AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'T', 'subject_id' => '1']);

    $this->artisan('bloxy:audit-anchor', ['--reason' => 'manual reset'])->assertExitCode(0);

    AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'T', 'subject_id' => '2']);

    $this->artisan('bloxy:audit-verify-chain')->assertExitCode(0);
});
