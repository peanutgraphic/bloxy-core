<?php

declare(strict_types=1);

namespace Bloxy\Core\Audit\Console;

use Bloxy\Core\Audit\ChainSigner;
use Illuminate\Console\Command;

class VerifyChainCommand extends Command
{
    protected $signature = 'bloxy:audit-verify-chain {--limit=10000 : Number of recent rows to verify}';

    protected $description = 'Verify the integrity of the most recent audit-log chain rows.';

    public function handle(ChainSigner $signer): int
    {
        $limit = (int) $this->option('limit');
        $result = $signer->verify($limit);

        if ($result->passed) {
            $this->info("OK — verified {$result->checked} rows, no tampering detected.");
            return self::SUCCESS;
        }

        $this->error("FAIL — chain broken at audit_log.id={$result->brokenAtId} (reason: {$result->reason}). Checked {$result->checked} rows.");
        return self::FAILURE;
    }
}
