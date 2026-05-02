<?php

declare(strict_types=1);

namespace Bloxy\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Server-side encrypted string cast.
 *
 * Encrypts the attribute on the way to the database and decrypts on retrieval
 * using the host application's encryption key (Laravel's `Crypt` facade).
 *
 * The server CAN decrypt these values. This protects against a stolen DB
 * dump but does NOT provide zero-knowledge encryption — for that, use
 * `Bloxy\Crypto\EnvelopeEncrypted` from the bloxy-crypto package.
 *
 * Use on a `text` (or larger) column — encrypted output is longer than the
 * plaintext.
 */
class ServerEncryptedString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::decryptString((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }
}
