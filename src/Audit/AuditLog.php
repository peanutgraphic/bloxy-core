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
        ];
    }
}
