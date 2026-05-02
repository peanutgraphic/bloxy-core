<?php

declare(strict_types=1);

namespace Bloxy\Core\Casts;

/**
 * @deprecated 0.x.0 Use ServerEncryptedString. Renamed to disambiguate from
 * the client-held envelope encryption shipping in bloxy-crypto. Will be
 * removed in the next minor version.
 */
final class EncryptedString extends ServerEncryptedString
{
}
