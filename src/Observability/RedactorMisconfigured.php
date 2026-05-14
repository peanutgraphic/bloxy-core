<?php

declare(strict_types=1);

namespace Bloxy\Core\Observability;

/**
 * Thrown when Redactor is constructed with a configuration that would
 * silently no-op the redaction path. B-11 (M4): an empty marker turns
 * every redacted value into an empty string (which an audit consumer
 * might mistake for "field not present"), and non-string allowlist
 * entries are unconditionally skipped during the per-key match — both
 * are programmer errors, not runtime conditions, so we fail loudly
 * during construction instead of producing audit rows that look
 * redacted but aren't.
 */
class RedactorMisconfigured extends \LogicException
{
    public static function emptyMarker(): self
    {
        return new self(
            'Redactor marker cannot be empty — an empty marker silently '
            . 'replaces sensitive values with "" and is indistinguishable '
            . 'from a missing field in downstream consumers.'
        );
    }

    public static function nonStringNeedle(mixed $needle): self
    {
        $type = get_debug_type($needle);

        return new self(
            "Redactor allowlist entries must be strings; got {$type}. "
            . 'Non-string needles are silently skipped during matching, '
            . 'which is a programmer error rather than a config choice.'
        );
    }
}
