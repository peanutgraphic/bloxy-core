<?php

declare(strict_types=1);

namespace Bloxy\Core\Observability;

class Redactor
{
    /** @var array<int, string> Pre-lowercased needles for substring matching. */
    private readonly array $allowlist;

    /**
     * @param array<int, string> $allowlist Case-insensitive substrings matched against keys.
     * @param string             $marker    Replacement string for redacted values.
     */
    public function __construct(
        array $allowlist,
        private readonly string $marker = '[REDACTED]',
    ) {
        // Lowercase once at construction so the per-key match doesn't
        // strtolower() each needle on every call.
        $this->allowlist = array_values(array_map('strtolower', $allowlist));
    }

    /**
     * Redact values whose keys match the allowlist.
     *
     * Scalars and null pass through unchanged. Arrays are walked recursively;
     * any string key containing an allowlist entry (case-insensitive) has its
     * value replaced by the marker.
     */
    /**
     * Maximum nested-array depth Redactor will walk. Adversarial 64KB
     * JSON payloads can encode ~10k levels of nesting via single-char
     * `{"a":` blocks; without a depth cap that overflows PHP's recursion
     * limit (default 256 stack frames). Realistic API payloads top out
     * around 10-15 levels, so 64 is generous AND safe.
     * S-14 (Pass 2 M4).
     */
    public const MAX_DEPTH = 64;

    public function redact(mixed $input): mixed
    {
        return $this->redactInternal($input, 0);
    }

    private function redactInternal(mixed $input, int $depth): mixed
    {
        if (! is_array($input)) {
            return $input;
        }

        if ($this->allowlist === []) {
            return $input;
        }

        if ($depth >= self::MAX_DEPTH) {
            return ['_truncated' => 'max_depth'];
        }

        $result = [];
        foreach ($input as $key => $value) {
            if (is_string($key) && $this->keyMatchesAllowlist($key)) {
                $result[$key] = $this->marker;
                continue;
            }

            $result[$key] = is_array($value)
                ? $this->redactInternal($value, $depth + 1)
                : $value;
        }

        return $result;
    }

    private function keyMatchesAllowlist(string $key): bool
    {
        $haystack = strtolower($key);

        foreach ($this->allowlist as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
