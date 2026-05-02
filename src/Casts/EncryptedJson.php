<?php

declare(strict_types=1);

namespace Bloxy\Core\Casts;

/**
 * @deprecated 0.x.0 Use ServerEncryptedJson. Renamed to disambiguate from
 * the client-held envelope encryption shipping in bloxy-crypto. Will be
 * removed in the next minor version.
 */
final class EncryptedJson extends ServerEncryptedJson
{
}
