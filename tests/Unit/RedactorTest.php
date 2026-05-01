<?php

declare(strict_types=1);

use Bloxy\Core\Observability\Redactor;

it('returns scalar values unchanged', function () {
    $r = new Redactor(['password'], '[REDACTED]');

    expect($r->redact('hello'))->toBe('hello');
    expect($r->redact(42))->toBe(42);
    expect($r->redact(null))->toBe(null);
    expect($r->redact(true))->toBe(true);
});

it('redacts a top-level allowlisted field', function () {
    $r = new Redactor(['password'], '[REDACTED]');

    $result = $r->redact(['username' => 'nat', 'password' => 'sekret']);

    expect($result)->toBe(['username' => 'nat', 'password' => '[REDACTED]']);
});

it('matches the allowlist case-insensitively', function () {
    $r = new Redactor(['password'], '[REDACTED]');

    $result = $r->redact(['Password' => 'sekret', 'PASSWORD' => 'sekret']);

    expect($result)->toBe(['Password' => '[REDACTED]', 'PASSWORD' => '[REDACTED]']);
});

it('matches the allowlist as substring (api_key matches my_api_key)', function () {
    $r = new Redactor(['api_key'], '[REDACTED]');

    $result = $r->redact(['my_api_key' => 'sk_live_abc', 'note' => 'hello']);

    expect($result)->toBe(['my_api_key' => '[REDACTED]', 'note' => 'hello']);
});

it('redacts nested array values recursively', function () {
    $r = new Redactor(['secret'], '[REDACTED]');

    $result = $r->redact([
        'user' => [
            'name' => 'nat',
            'secret' => 'shhh',
            'profile' => [
                'secret' => 'inner',
            ],
        ],
    ]);

    expect($result)->toBe([
        'user' => [
            'name' => 'nat',
            'secret' => '[REDACTED]',
            'profile' => [
                'secret' => '[REDACTED]',
            ],
        ],
    ]);
});

it('preserves non-string keys without crashing', function () {
    $r = new Redactor(['password'], '[REDACTED]');

    $result = $r->redact([0 => 'a', 1 => 'b', 'password' => 'sekret']);

    expect($result)->toBe([0 => 'a', 1 => 'b', 'password' => '[REDACTED]']);
});

it('handles an empty allowlist by returning input unchanged', function () {
    $r = new Redactor([], '[REDACTED]');

    $result = $r->redact(['password' => 'sekret', 'name' => 'nat']);

    expect($result)->toBe(['password' => 'sekret', 'name' => 'nat']);
});

it('uses the configured marker', function () {
    $r = new Redactor(['password'], '***');

    $result = $r->redact(['password' => 'sekret']);

    expect($result)->toBe(['password' => '***']);
});
