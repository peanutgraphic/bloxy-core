<?php

declare(strict_types=1);

namespace Bloxy\Core\Audit;

use Bloxy\Core\Casts\ServerEncryptedJson;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit log entry.
 *
 * One row per state-changing event (Eloquent created/updated/deleted via
 * HasAudit, an explicit recordRead() call, or an HTTP request via
 * AuditMiddleware). The `changes` column is encrypted at rest via the
 * EncryptedJson cast so before/after diffs containing PII never sit on disk
 * in plaintext.
 */
class AuditLog extends Model
{
    protected $table = 'audit_log';

    protected $guarded = [];

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'happened_at' => 'datetime',
            'changes' => ServerEncryptedJson::class,
            'meta' => 'array',
            'signing_key_id' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (AuditLog $row): bool|null {
            // Skip on updates — chain signing is insert-only.
            if ($row->exists) {
                return null;
            }

            // Resolve via container so the cast/key-lookup honors the host app's config.
            // Skip if bloxy is not booted (e.g. raw Eloquent in a non-Laravel context).
            if (! function_exists('app')) {
                return null;
            }

            try {
                $signer = app(ChainSigner::class);
            } catch (\Throwable $e) {
                // When the chain is enabled, a resolver failure must surface —
                // silently saving unsigned rows in a chain-enabled deployment
                // would punch holes the verifier can't catch.
                if ((bool) (function_exists('config') ? config('bloxy.audit.signed_chain', false) : false)) {
                    throw $e;
                }
                return null;
            }

            // signAndSave() performs the INSERT inside the same transaction
            // that holds the previous-row lock. Returning false here aborts
            // Eloquent's outer save so the row is not double-inserted.
            if ($signer->signAndSave($row)) {
                return false;
            }

            return null;
        });
    }
}
