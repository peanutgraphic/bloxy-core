<?php

declare(strict_types=1);

use Bloxy\Core\Observability\Redactor;
use Bloxy\Core\Observability\RedactorMisconfigured;

it('throws RedactorMisconfigured when marker is empty (B-11)', function () {
    expect(fn () => new Redactor(['password'], ''))
        ->toThrow(RedactorMisconfigured::class);
});

it('throws RedactorMisconfigured when an allowlist entry is not a string (B-11)', function () {
    /** @phpstan-ignore-next-line — intentionally wrong type to exercise the guard */
    expect(fn () => new Redactor(['password', 42], '[REDACTED]'))
        ->toThrow(RedactorMisconfigured::class);
});

it('still accepts an empty allowlist (valid "no redaction" config)', function () {
    $r = new Redactor([], '[REDACTED]');
    expect($r->redact(['password' => 'p']))->toBe(['password' => 'p']);
});
