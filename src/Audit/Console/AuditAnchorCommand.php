<?php

declare(strict_types=1);

namespace Bloxy\Core\Audit\Console;

use Bloxy\Core\Audit\AuditLog;
use Illuminate\Console\Command;

class AuditAnchorCommand extends Command
{
    protected $signature = 'bloxy:audit-anchor {--reason= : Why this anchor is being written (required)}';

    protected $description = 'Write a chain_anchor row that the verifier treats as a legitimate chain break.';

    public function handle(): int
    {
        $reason = $this->option('reason');

        if ($reason === null || $reason === '') {
            $this->error('--reason is required. Anchors must be auditable.');
            return self::FAILURE;
        }

        $anchor = AuditLog::create([
            'happened_at' => now(),
            'action' => 'chain_anchor',
            'meta' => ['reason' => $reason],
        ]);

        $this->info("Anchor written at audit_log.id={$anchor->id} with reason: {$reason}");
        return self::SUCCESS;
    }
}
