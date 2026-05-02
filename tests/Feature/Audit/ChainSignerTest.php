<?php

declare(strict_types=1);

use Bloxy\Core\Audit\AuditLog;
use Bloxy\Core\Audit\ChainSigner;

beforeEach(function (): void {
    config()->set('bloxy.audit.signed_chain', true);
    config()->set('bloxy.audit.hmac_keys', [
        1 => str_repeat('a', 64),
    ]);
    config()->set('bloxy.audit.active_signing_key_id', 1);
});

it('signs a fresh audit row with HMAC-SHA-256', function () {
    $signer = app(ChainSigner::class);

    $row = AuditLog::create([
        'happened_at' => now(),
        'action' => 'created',
        'subject_type' => 'Test',
        'subject_id' => '1',
    ]);

    expect($row->signature)->toBeString();
    expect(strlen($row->signature))->toBe(64);              // hex of SHA-256
    expect($row->signing_key_id)->toBe(1);
});

it('produces a chain where each row signs the previous row', function () {
    $first = AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'Test', 'subject_id' => '1']);
    $second = AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'Test', 'subject_id' => '2']);
    $third = AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'Test', 'subject_id' => '3']);

    $signer = app(ChainSigner::class);
    $expectedSecond = hash_hmac('sha256', $first->signature . $signer->canonicalize($second), str_repeat('a', 64));
    $expectedThird  = hash_hmac('sha256', $second->signature . $signer->canonicalize($third), str_repeat('a', 64));

    expect($second->signature)->toBe($expectedSecond);
    expect($third->signature)->toBe($expectedThird);
});

it('verifies an intact chain', function () {
    AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'T', 'subject_id' => '1']);
    AuditLog::create(['happened_at' => now(), 'action' => 'updated', 'subject_type' => 'T', 'subject_id' => '1']);
    AuditLog::create(['happened_at' => now(), 'action' => 'deleted', 'subject_type' => 'T', 'subject_id' => '1']);

    $result = app(ChainSigner::class)->verify();

    expect($result->passed)->toBeTrue();
    expect($result->checked)->toBe(3);
    expect($result->brokenAtId)->toBeNull();
});

it('detects a tampered row via signature mismatch', function () {
    AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'T', 'subject_id' => '1']);
    $tampered = AuditLog::create(['happened_at' => now(), 'action' => 'updated', 'subject_type' => 'T', 'subject_id' => '1']);
    AuditLog::create(['happened_at' => now(), 'action' => 'deleted', 'subject_type' => 'T', 'subject_id' => '1']);

    // Tamper directly via the query builder to bypass the model's saving event.
    \DB::table('audit_log')
        ->where('id', $tampered->id)
        ->update(['action' => 'tampered']);

    $result = app(ChainSigner::class)->verify();

    expect($result->passed)->toBeFalse();
    expect($result->brokenAtId)->toBe($tampered->id);
});

it('does NOT sign when chain is disabled', function () {
    config()->set('bloxy.audit.signed_chain', false);

    $row = AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'T', 'subject_id' => '1']);

    expect($row->signature)->toBeNull();
    expect($row->signing_key_id)->toBeNull();
});

it('uses the active signing key id when set explicitly', function () {
    config()->set('bloxy.audit.hmac_keys', [
        1 => str_repeat('a', 64),
        2 => str_repeat('b', 64),
    ]);
    config()->set('bloxy.audit.active_signing_key_id', 2);

    $row = AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'T', 'subject_id' => '1']);

    expect($row->signing_key_id)->toBe(2);
    // Verification should still pass because verify uses signing_key_id to look up the right key.
    expect(app(ChainSigner::class)->verify()->passed)->toBeTrue();
});

it('signs and verifies rows that have a changes payload', function () {
    $row1 = AuditLog::create([
        'happened_at' => now(),
        'action' => 'created',
        'subject_type' => 'Test',
        'subject_id' => '1',
        'changes' => ['before' => null, 'after' => ['name' => 'Alice']],
    ]);

    $row2 = AuditLog::create([
        'happened_at' => now(),
        'action' => 'updated',
        'subject_type' => 'Test',
        'subject_id' => '1',
        'changes' => ['before' => ['name' => 'Alice'], 'after' => ['name' => 'Bob']],
    ]);

    expect($row1->signature)->toBeString()->and(strlen($row1->signature))->toBe(64);
    expect($row2->signature)->toBeString()->and(strlen($row2->signature))->toBe(64);

    // Verify the chain is intact even with encrypted-cast columns populated.
    $result = app(ChainSigner::class)->verify();
    expect($result->passed)->toBeTrue();
    expect($result->checked)->toBe(2);

    // Tamper-detection still fires when one of the changes-bearing rows is mutated.
    \DB::table('audit_log')->where('id', $row2->id)->update(['action' => 'tampered']);
    $afterTamper = app(ChainSigner::class)->verify();
    expect($afterTamper->passed)->toBeFalse();
    expect($afterTamper->brokenAtId)->toBe($row2->id);
});
