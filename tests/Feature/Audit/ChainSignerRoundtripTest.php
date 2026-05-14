<?php

declare(strict_types=1);

use Bloxy\Core\Audit\AuditLog;
use Bloxy\Core\Audit\ChainSigner;

beforeEach(function (): void {
    config()->set('bloxy.audit.signed_chain', true);
    config()->set('bloxy.audit.hmac_keys', [1 => str_repeat('a', 64)]);
    config()->set('bloxy.audit.active_signing_key_id', 1);
    config()->set('bloxy.audit.chain_version', 2);
});

it('stamps each signed row with the configured chain_version', function () {
    $row = AuditLog::create([
        'happened_at' => now(),
        'action' => 'created',
        'subject_type' => 'Test',
        'subject_id' => '1',
        'changes' => ['after' => ['name' => 'Alice']],
    ]);

    expect($row->chain_version)->toBe(2);
    expect(\DB::table('audit_log')->where('id', $row->id)->value('chain_version'))->toBe(2);
});

it('excludes chain_version from canonical bytes so the marker can change without breaking the chain', function () {
    $row = AuditLog::create([
        'happened_at' => now(),
        'action' => 'created',
        'subject_type' => 'Test',
        'subject_id' => '1',
    ]);
    $originalSig = $row->signature;

    // Operator changes the recorded marker; the chain must still verify
    // because chain_version is metadata about the signing run, not part of
    // the signed payload.
    \DB::table('audit_log')->where('id', $row->id)->update(['chain_version' => 99]);

    expect(app(ChainSigner::class)->verify()->passed)->toBeTrue();
    expect(AuditLog::find($row->id)->signature)->toBe($originalSig);
});

it('survives save → reload → verify with unicode + nested changes', function () {
    AuditLog::create([
        'happened_at' => now(),
        'action' => 'created',
        'subject_type' => 'Profile',
        'subject_id' => '42',
        'changes' => [
            'after' => [
                'name' => 'Renée 北野 🪨',
                'tags' => ['α', 'β', 'γ'],
                'nested' => ['deep' => ['deeper' => ['leaf' => 'こんにちは']]],
                'path' => '/forward/slashes/and "quoted"',
            ],
        ],
    ]);
    AuditLog::create([
        'happened_at' => now(),
        'action' => 'updated',
        'subject_type' => 'Profile',
        'subject_id' => '42',
        'changes' => ['before' => ['name' => 'Renée 北野 🪨'], 'after' => ['name' => 'René']],
    ]);

    // Reload from disk to exercise the verify-time path (cast get() from
    // stored ciphertext, not in-memory array).
    $rows = AuditLog::query()->orderBy('id')->get();
    expect($rows)->toHaveCount(2);
    expect($rows[0]->changes['after']['nested']['deep']['deeper']['leaf'])->toBe('こんにちは');

    $result = app(ChainSigner::class)->verify();
    expect($result->passed)->toBeTrue();
    expect($result->checked)->toBe(2);
});

it('verifies a signed chain after a fresh Eloquent reload (no in-memory state)', function () {
    $first = AuditLog::create(['happened_at' => now(), 'action' => 'created', 'subject_type' => 'T', 'subject_id' => '1', 'meta' => ['source' => 'web']]);
    $second = AuditLog::create(['happened_at' => now(), 'action' => 'updated', 'subject_type' => 'T', 'subject_id' => '1', 'changes' => ['after' => ['v' => 2]]]);

    // Drop the in-memory Eloquent state entirely — verify reads each row
    // from the DB and reconstructs the canonical bytes through the cast
    // pipeline. This is the path B-2 exercised; the fix must keep it
    // byte-identical to the sign-time path.
    unset($first, $second);

    expect(app(ChainSigner::class)->verify()->passed)->toBeTrue();
});
