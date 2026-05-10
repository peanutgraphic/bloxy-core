<?php

declare(strict_types=1);

use Bloxy\Core\Support\Base64Url;

it('encodes bytes to base64url', function () {
    expect(Base64Url::encode("Hello"))->toBe('SGVsbG8');
    expect(Base64Url::encode(""))->toBe('');
    expect(Base64Url::encode("\xff\xff\xff"))->toBe('____');
    expect(Base64Url::encode("\xfb\xff\xff"))->toBe('-___');
});

it('decodes base64url to bytes', function () {
    expect(Base64Url::decode('SGVsbG8'))->toBe('Hello');
    expect(Base64Url::decode(''))->toBe('');
    expect(Base64Url::decode('____'))->toBe("\xff\xff\xff");
    expect(Base64Url::decode('-___'))->toBe("\xfb\xff\xff");
});

it('round-trips arbitrary bytes', function () {
    $bytes = random_bytes(64);
    expect(Base64Url::decode(Base64Url::encode($bytes)))->toBe($bytes);
});

it('produces output without padding', function () {
    expect(Base64Url::encode("Hello"))->not->toContain('=');
    expect(Base64Url::encode("Hi"))->not->toContain('=');
});

it('decodes input with optional trailing padding', function () {
    expect(Base64Url::decode('SGVsbG8='))->toBe('Hello');
});

it('throws on invalid base64url input', function () {
    Base64Url::decode('not valid base64!!!');
})->throws(InvalidArgumentException::class);
