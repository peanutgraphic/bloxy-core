<?php

declare(strict_types=1);

namespace Bloxy\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypted string cast.
 *
 * Encrypts the attribute on the way to the database and decrypts on retrieval.
 * Uses the host application's encryption key (Laravel's `Crypt` facade).
 *
 * Use on a `text` (or larger) column — encrypted output is longer than the
 * plaintext.
 */
class EncryptedString implements CastsAttributes
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
