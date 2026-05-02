<?php

declare(strict_types=1);

use Bloxy\Core\Audit\AuditLog;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    // Make sure chain signing isn't on for these tests; they exercise body capture only.
    config()->set('bloxy.audit.signed_chain', false);
});

it('does NOT capture the body when capture_request_body is null (default)', function () {
    config()->set('bloxy.audit.capture_request_body', null);

    Route::middleware(['bloxy.audit'])->post('/test/echo', fn () => response()->json(['ok' => true]));

    $this->postJson('/test/echo', ['email' => 'a@b.com', 'password' => 'secret'])->assertOk();

    $entry = AuditLog::query()->where('action', 'http_request')->first();
    expect($entry)->not->toBeNull();
    expect($entry->meta)->not->toHaveKey('body');
});

it('captures and redacts the body when set to "redacted"', function () {
    config()->set('bloxy.audit.capture_request_body', 'redacted');

    Route::middleware(['bloxy.audit'])->post('/test/echo', fn () => response()->json(['ok' => true]));

    $this->postJson('/test/echo', ['email' => 'a@b.com', 'password' => 'secret'])->assertOk();

    $entry = AuditLog::query()->where('action', 'http_request')->first();
    expect($entry->meta['body'] ?? null)->toBe([
        'email' => 'a@b.com',
        'password' => '[REDACTED]',
    ]);
});

it('captures body without redaction when set to "full"', function () {
    config()->set('bloxy.audit.capture_request_body', 'full');

    Route::middleware(['bloxy.audit'])->post('/test/echo', fn () => response()->json(['ok' => true]));

    $this->postJson('/test/echo', ['email' => 'a@b.com', 'password' => 'secret'])->assertOk();

    $entry = AuditLog::query()->where('action', 'http_request')->first();
    expect($entry->meta['body'] ?? null)->toBe([
        'email' => 'a@b.com',
        'password' => 'secret',
    ]);
});

it('truncates bodies larger than request_body_max_bytes', function () {
    config()->set('bloxy.audit.capture_request_body', 'redacted');
    config()->set('bloxy.audit.request_body_max_bytes', 100);

    Route::middleware(['bloxy.audit'])->post('/test/echo', fn () => response()->json(['ok' => true]));

    $bigPayload = ['data' => str_repeat('A', 200)];
    $this->postJson('/test/echo', $bigPayload)->assertOk();

    $entry = AuditLog::query()->where('action', 'http_request')->first();
    expect($entry->meta['body_truncated'] ?? null)->toBeTrue();
    expect($entry->meta['body'] ?? null)->toBeNull();
});

it('omits multipart bodies regardless of mode', function () {
    config()->set('bloxy.audit.capture_request_body', 'full');

    Route::middleware(['bloxy.audit'])->post('/test/upload', fn () => response()->json(['ok' => true]));

    $this->post('/test/upload', [
        'note' => 'hi',
        'file' => \Illuminate\Http\UploadedFile::fake()->create('a.txt', 1),
    ])->assertOk();

    $entry = AuditLog::query()->where('action', 'http_request')->first();
    expect($entry->meta['body'] ?? null)->toBe(['_omitted' => 'multipart/form-data']);
});
