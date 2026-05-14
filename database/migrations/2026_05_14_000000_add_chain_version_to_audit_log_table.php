<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags each signed audit_log row with the canonicalization version the
 * signer used. M4 B-2 changed the canonical-bytes contract (routed
 * through the configured cast pipeline). Without a version marker,
 * future verifiers can't distinguish v1 (pre-B-2) from v2 (post-B-2)
 * rows during a re-anchor / re-deploy.
 *
 * Nullable + null default = legacy rows stay green. Pre-board audit
 * 2026-05-11 confirmed zero signed rows in any consumer prod, so this
 * is forward-only by design — no backfill needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->smallInteger('chain_version')->nullable()->after('signing_key_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->dropColumn('chain_version');
        });
    }
};
