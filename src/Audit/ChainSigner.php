<?php

declare(strict_types=1);

namespace Bloxy\Core\Audit;

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

        // Normalize the `meta` JSON column key order for stable signing.
        // The `meta` column is cast to array but stored as a JSON string;
        // re-decode + ksort + re-encode so consumers building `meta` with
        // different key order across writes don't break the canonical form.
        if (isset($attrs['meta']) && is_string($attrs['meta'])) {
            $decoded = json_decode($attrs['meta'], associative: true);
            if (is_array($decoded)) {
                $this->ksortRecursive($decoded);
                $attrs['meta'] = json_encode(
                    $decoded,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );
            }
        } elseif (isset($attrs['meta']) && is_array($attrs['meta'])) {
            // At sign time before save, getAttributes() may return the raw
            // array rather than the encoded string. Normalize the same way.
            $this->ksortRecursive($attrs['meta']);
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
