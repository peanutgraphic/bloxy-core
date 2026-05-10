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
        $padded = strtr($base64url, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64url input');
        }
        return $decoded;
    }
}
