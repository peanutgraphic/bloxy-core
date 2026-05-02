<?php

declare(strict_types=1);

use Bloxy\Core\Audit\AuditLog;

beforeEach(function (): void {
    config()->set('bloxy.audit.signed_chain', true);
    config()->set('bloxy.audit.hmac_keys', [1 => str_repeat('a', 64)]);
    config()->set('bloxy.audit.active_signing_key_id', 1);
});

it('exits 0 and prints OK on a clean chain', function () {
    AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'T', 'subject_id' => '1']);
    AuditLog::create(['happened_at' => now(), 'action' => 'updated', 'subject_type' => 'T', 'subject_id' => '1']);

    $this->artisan('bloxy:audit-verify-chain')
        ->expectsOutputToContain('OK')
        ->assertExitCode(0);
});

it('exits non-zero and prints the broken row id on tampered data', function () {
    AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'T', 'subject_id' => '1']);
    $tampered = AuditLog::create(['happened_at' => now(), 'action' => 'updated', 'subject_type' => 'T', 'subject_id' => '1']);

    \DB::table('audit_log')->where('id', $tampered->id)->update(['action' => 'tampered']);

    $this->artisan('bloxy:audit-verify-chain')
        ->expectsOutputToContain("audit_log.id={$tampered->id}")
        ->assertExitCode(1);
});
