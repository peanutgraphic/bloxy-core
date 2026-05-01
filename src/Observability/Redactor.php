<?php

declare(strict_types=1);

namespace Bloxy\Core\Observability;

class Redactor
{
    /**
     * @param array<int, string> $allowlist Case-insensitive substrings to match against keys.
     * @param string             $marker    Replacement string for redacted values.
     */
    public function __construct(
        private readonly array $allowlist,
        private readonly string $marker = '[REDACTED]',
    ) {
    }

    /**
     * Redact values whose keys match the allowlist.
     *
     * Scalars and null pass through unchanged. Arrays are walked recursively;
     * any string key containing an allowlist entry (case-insensitive) has its
     * value replaced by the marker.
     */
    public function redact(mixed $input): mixed
    {
        if (! is_array($input)) {
            return $input;
        }

        if ($this->allowlist === []) {
            return $input;
        }

        $result = [];
        foreach ($input as $key => $value) {
            if (is_string($key) && $this->keyMatchesAllowlist($key)) {
                $result[$key] = $this->marker;
                continue;
            }

            $result[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $result;
    }

    private function keyMatchesAllowlist(string $key): bool
    {
        $haystack = strtolower($key);

        foreach ($this->allowlist as $needle) {
            if (str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
