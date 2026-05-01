<?php

declare(strict_types=1);

namespace Bloxy\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypted JSON cast.
 *
 * Encrypts a JSON representation of the attribute on the way to the database
 * and decrypts + decodes on retrieval. Suitable for arrays, scalars, and any
 * json_encode-compatible value. Use on a `text` (or larger) column.
 */
class EncryptedJson implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $json = Crypt::decryptString((string) $value);

        /** @var mixed $decoded */
        $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return Crypt::encryptString($json);
    }
}
