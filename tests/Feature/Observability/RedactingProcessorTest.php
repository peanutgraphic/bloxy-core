<?php

declare(strict_types=1);

use Bloxy\Core\Observability\RedactingProcessor;
use Bloxy\Core\Observability\Redactor;
use Monolog\Level;
use Monolog\LogRecord;

beforeEach(function (): void {
    $this->redactor = new Redactor(
        allowlist: ['password', 'token', 'secret', 'authorization'],
        marker: '[REDACTED]',
    );
    $this->processor = new RedactingProcessor($this->redactor);
});

it('redacts allowlisted keys in context', function () {
    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'test',
        level: Level::Warning,
        message: 'login failed',
        context: ['email' => 'a@b.com', 'password' => 'secret123'],
        extra: [],
    );

    $processed = ($this->processor)($record);

    expect($processed->context)->toBe([
        'email' => 'a@b.com',
        'password' => '[REDACTED]',
    ]);
});

it('redacts allowlisted keys in extra', function () {
    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'request',
        context: [],
        extra: ['headers' => ['authorization' => 'Bearer abc'], 'user' => 'alice'],
    );

    $processed = ($this->processor)($record);

    expect($processed->extra['headers']['authorization'] ?? null)->toBe('[REDACTED]');
    expect($processed->extra['user'] ?? null)->toBe('alice');
});

it('passes through records with no redactable keys unchanged', function () {
    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'hello',
        context: ['foo' => 'bar'],
        extra: ['baz' => 1],
    );

    $processed = ($this->processor)($record);

    expect($processed->context)->toBe(['foo' => 'bar']);
    expect($processed->extra)->toBe(['baz' => 1]);
});
