<?php

declare(strict_types=1);

use Bloxy\Core\Audit\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('persists a row with all expected columns', function () {
    $log = AuditLog::create([
        'happened_at' => Carbon::parse('2026-04-30 12:00:00'),
        'actor_type' => 'user',
        'actor_id' => '42',
        'action' => 'created',
        'subject_type' => 'App\\Models\\Note',
        'subject_id' => '7',
        'changes' => ['before' => null, 'after' => ['title' => 'hello']],
        'ip_address' => '203.0.113.10',
        'user_agent' => 'Mozilla/5.0',
        'request_id' => '550e8400-e29b-41d4-a716-446655440000',
        'meta' => ['source' => 'controller'],
    ]);

    expect($log->id)->toBeInt();
    expect($log->actor_type)->toBe('user');
    expect($log->actor_id)->toBe('42');
    expect($log->action)->toBe('created');
    expect($log->subject_type)->toBe('App\\Models\\Note');
    expect($log->subject_id)->toBe('7');
    expect($log->ip_address)->toBe('203.0.113.10');
    expect($log->user_agent)->toBe('Mozilla/5.0');
    expect($log->request_id)->toBe('550e8400-e29b-41d4-a716-446655440000');
});

it('encrypts the changes column on disk via EncryptedJson cast', function () {
    $log = AuditLog::create([
        'happened_at' => Carbon::now(),
        'action' => 'updated',
        'changes' => ['before' => ['title' => 'old'], 'after' => ['title' => 'new-secret-content']],
    ]);

    $rawColumn = DB::table('audit_log')->where('id', $log->id)->value('changes');

    expect($rawColumn)->not->toContain('new-secret-content');
    expect($rawColumn)->not->toContain('title');

    $reloaded = AuditLog::find($log->id);
    expect($reloaded->changes)->toBe([
        'before' => ['title' => 'old'],
        'after' => ['title' => 'new-secret-content'],
    ]);
});

it('round-trips meta as plain JSON (not encrypted)', function () {
    $log = AuditLog::create([
        'happened_at' => Carbon::now(),
        'action' => 'http_request',
        'meta' => ['method' => 'POST', 'path' => '/foo'],
    ]);

    $reloaded = AuditLog::find($log->id);
    expect($reloaded->meta)->toBe(['method' => 'POST', 'path' => '/foo']);
});

it('casts happened_at to a Carbon instance', function () {
    $log = AuditLog::create([
        'happened_at' => '2026-04-30 12:00:00',
        'action' => 'created',
    ]);

    expect($log->happened_at)->toBeInstanceOf(Carbon::class);
});

it('uses the audit_log table name', function () {
    expect((new AuditLog())->getTable())->toBe('audit_log');
});

it('allows null actor and null subject', function () {
    $log = AuditLog::create([
        'happened_at' => Carbon::now(),
        'action' => 'system_event',
    ]);

    expect($log->actor_id)->toBeNull();
    expect($log->actor_type)->toBeNull();
    expect($log->subject_id)->toBeNull();
    expect($log->subject_type)->toBeNull();
});
