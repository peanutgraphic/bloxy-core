<?php

declare(strict_types=1);

namespace Bloxy\Core\Audit;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChainSigner
{
    /**
     * Sign an AuditLog row and persist it inside the same transaction that
     * holds the previous-row lock. Returns true if this call performed the
     * INSERT (caller must NOT call save() again — return false from the
     * `saving` listener to abort Eloquent's outer save). Returns false when
     * chain signing is disabled (caller proceeds with normal save).
     *
     * Holding the lock across the INSERT is what prevents two concurrent
     * writers from both reading the same `prev`, signing against it, and
     * producing duplicate chain links. The previous design released the
     * lock before the INSERT, opening a race window.
     */
    public function signAndSave(AuditLog $row): bool
    {
        if (! (bool) config('bloxy.audit.signed_chain', false)) {
            return false;
        }

        $activeKeyId = (int) config('bloxy.audit.active_signing_key_id', 1);
        $key = $this->keyFor($activeKeyId);

        // Serialize via the previous row's lock. Works on Postgres
        // (real row lock) and degrades safely on SQLite (single-writer DB).
        // The INSERT happens inside the same transaction so the lock is
        // held until the new row is committed — no race window between
        // reading `prev` and writing the new row.
        DB::transaction(function () use ($row, $activeKeyId, $key): void {
            $prev = AuditLog::query()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $prevSignature = $prev?->signature ?? '';
            $row->signature = hash_hmac('sha256', $prevSignature . $this->canonicalize($row), $key);
            $row->signing_key_id = $activeKeyId;
            // M4 B-2: tag the row with the canonicalization version that
            // produced these bytes, so future verifiers can detect mixed
            // v1/v2 spans during a re-anchor without re-hashing every row.
            $row->chain_version = (int) config('bloxy.audit.chain_version', 2);

            // Persist quietly so we don't re-enter the saving event.
            $row->saveQuietly();
        });

        return true;
    }

    /**
     * @deprecated Use signAndSave(); kept for backward compat. Sets the
     * signature fields on the row but does NOT save — and the lock is
     * released before any subsequent save by the caller, which is the
     * race condition that signAndSave() exists to fix.
     */
    public function sign(AuditLog $row): void
    {
        $this->signAndSave($row);
    }

    /**
     * Verify the most recent $batchSize rows. Returns VerifyResult with
     * $passed=false and $brokenAtId set to the first row that fails.
     * Stops at chain_anchor rows — anchors legitimately reset the chain.
     */
    public function verify(int $batchSize = 10000): VerifyResult
    {
        $rows = AuditLog::query()
            ->orderByDesc('id')
            ->limit($batchSize)
            ->get()
            ->reverse()        // walk forward
            ->values();

        $checked = 0;
        $prevSignature = '';

        foreach ($rows as $row) {
            $checked++;

            // Anchor rows reset the chain; treat them as a legitimate break.
            if ($row->action === 'chain_anchor') {
                $prevSignature = $row->signature ?? '';
                continue;
            }

            // Skip rows from before the chain feature was enabled.
            if ($row->signature === null) {
                $prevSignature = '';
                continue;
            }

            $key = $this->keyFor((int) $row->signing_key_id);
            $expected = hash_hmac('sha256', $prevSignature . $this->canonicalize($row), $key);

            if (! hash_equals($expected, (string) $row->signature)) {
                return new VerifyResult(
                    passed: false,
                    checked: $checked,
                    brokenAtId: (int) $row->id,
                    reason: 'signature mismatch',
                );
            }

            $prevSignature = $row->signature;
        }

        return new VerifyResult(passed: true, checked: $checked);
    }

    /**
     * Canonical JSON for signing: every column except id, signature, created_at,
     * updated_at, signing_key_id; keys sorted alphabetically. Stable across
     * PHP versions because we sort and use JSON_UNESCAPED_UNICODE +
     * JSON_UNESCAPED_SLASHES (no implementation-defined escape order).
     *
     * Datetime attributes are normalized to "Y-m-d H:i:s" so the canonical
     * form is identical whether the attribute is a Carbon instance (at signing
     * time) or a plain string (when re-loaded from the database for verification).
     */
    public function canonicalize(AuditLog $row): string
    {
        $attrs = $row->getAttributes();

        unset(
            $attrs['id'],
            $attrs['signature'],
            $attrs['created_at'],
            $attrs['updated_at'],
            $attrs['signing_key_id'],
            $attrs['chain_version'],
        );

        // Normalize date/datetime values to a stable string form so that a Carbon
        // instance at sign-time serializes identically to the stored DB string
        // at verify-time.
        foreach ($attrs as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $attrs[$key] = Carbon::instance($value)->format('Y-m-d H:i:s');
            }
        }

        // Remove null values so that the canonical form is the same whether the
        // model is in-memory at signing time (only set attributes present) or
        // freshly loaded from the database (all columns present, unset ones null).
        $attrs = array_filter($attrs, fn ($v) => $v !== null);

        // Normalize cast-aware JSON columns to a deterministic string form.
        // The same logical row appears as different attribute shapes at
        // sign-time (raw PHP array, cast set() has not run) and verify-time
        // (stored DB form: ciphertext for `changes`, JSON string for `meta`).
        // Both paths must canonicalize to identical bytes, so we route through
        // the model's own cast pipeline to resolve each to its plaintext form,
        // then re-encode with ksort'd keys.
        $casts = $row->getCasts();
        foreach (['changes', 'meta'] as $castAware) {
            if (isset($attrs[$castAware])) {
                $attrs[$castAware] = $this->canonicalPlaintext(
                    $row,
                    $castAware,
                    $attrs[$castAware],
                    $casts[$castAware] ?? null,
                    $attrs,
                );
            }
        }

        ksort($attrs);

        return json_encode(
            $attrs,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    private function keyFor(int $keyId): string
    {
        $keys = (array) config('bloxy.audit.hmac_keys', []);

        if (! isset($keys[$keyId]) || ! is_string($keys[$keyId]) || $keys[$keyId] === '') {
            throw new RuntimeException(
                "bloxy.audit.hmac_keys is missing key id {$keyId}; chain signing cannot proceed."
            );
        }

        return $keys[$keyId];
    }

    /**
     * Resolve a cast-aware attribute to its plaintext canonical form by
     * routing through the model's own cast pipeline.
     *
     * Sign-time receives the raw PHP value the caller assigned (typically an
     * array, before the cast's set() has serialized it). Verify-time receives
     * the stored DB form (a ciphertext string for `ServerEncryptedJson`, or a
     * JSON string for the built-in `array` cast). To produce identical bytes
     * on both paths, we invoke the model's configured cast `get()` on string
     * inputs — which is the same code path Eloquent uses when reading the
     * attribute — and skip it for array inputs that are already plaintext.
     *
     * This deliberately avoids calling `Crypt::decryptString` directly. If
     * the cast ever changes (per-tenant KEK wrapper, version envelope, etc.)
     * the signer auto-adopts the new contract without touching this code.
     */
    private function canonicalPlaintext(
        \Illuminate\Database\Eloquent\Model $row,
        string $key,
        mixed $value,
        ?string $castDefinition,
        array $attributes,
    ): string {
        if (is_string($value) && $castDefinition !== null) {
            $plaintext = $this->runCastGet($castDefinition, $row, $key, $value, $attributes);
            if ($plaintext !== null) {
                $value = $plaintext;
            }
        }

        if (is_array($value)) {
            $this->ksortRecursive($value);

            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }

        return is_string($value) ? $value : (string) $value;
    }

    /**
     * Dispatch to the model's configured cast `get()` for a stored value.
     * Supports CastsAttributes classes (e.g. ServerEncryptedJson) and the
     * built-in `array` / `json` casts. Returns null if the cast cannot
     * resolve the value so the caller signs the raw string as-is — which
     * keeps legacy pre-cast rows verifiable under their original form.
     */
    private function runCastGet(
        string $castDefinition,
        \Illuminate\Database\Eloquent\Model $row,
        string $key,
        string $value,
        array $attributes,
    ): mixed {
        [$castClass, $params] = array_pad(explode(':', $castDefinition, 2), 2, null);

        if (in_array($castClass, ['array', 'json'], true)) {
            try {
                $decoded = json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : null;
            } catch (\Throwable) {
                return null;
            }
        }

        if (! class_exists($castClass) || ! is_subclass_of($castClass, CastsAttributes::class)) {
            return null;
        }

        try {
            $cast = $params !== null
                ? new $castClass(...explode(',', $params))
                : new $castClass();

            return $cast->get($row, $key, $value, $attributes);
        } catch (\Throwable $e) {
            // Surface decrypt fall-through (APP_KEY rotation, missing KEK,
            // corrupt ciphertext) instead of silently signing the raw value.
            // Returning null lets canonicalPlaintext sign the stored bytes
            // as-is, which keeps legacy pre-cast rows verifiable, but the
            // operator gets a Sentry/log breadcrumb that something is off.
            if (function_exists('report')) {
                report($e);
            }

            return null;
        }
    }

    private function ksortRecursive(array &$value): void
    {
        foreach ($value as &$child) {
            if (is_array($child)) {
                $this->ksortRecursive($child);
            }
        }
        ksort($value);
    }
}
