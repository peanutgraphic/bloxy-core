<?php

declare(strict_types=1);

namespace Bloxy\Core\Support;

use InvalidArgumentException;

/**
 * RFC 4648 §5 base64url encode/decode (no padding, `-` and `_` instead of
 * `+` and `/`). Mirrors `@peanutgraphic/bloxy-crypto`'s `Base64Url` so PHP
 * and JS sides round-trip the same bytes.
 */
final class Base64Url
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $base64url): string
    {
        // B-8: parity with the JS twin's S-21 guard. base64_decode() — even
        // with strict=true — accepts '+' and '/', so a base64 (non-base64url)
        // string would silently decode here and feed unexpected bytes into
        // HKDF/Argon2id without error. Validate the base64url alphabet (with
        // optional trailing padding) before transforming.
        if (preg_match('/^[A-Za-z0-9_-]*={0,2}$/', $base64url) !== 1) {
            throw new InvalidArgumentException('Invalid base64url input');
        }
        $padded = strtr($base64url, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64url input');
        }
        return $decoded;
    }
}
