<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Audit;

/**
 * Canonical audit-log action strings for agent-related operations. Mirrors
 * the pattern used by Bloxy\Crypto\Audit\CryptoAuditActions — consumers
 * (e.g., Tracy) reference these constants when emitting audit_log rows from
 * agent invocation pipelines.
 */
final class AgentAuditActions
{
    public const AGENT_INVOKE = 'AGENT_INVOKE';
    public const AGENT_INVOKE_DENIED = 'AGENT_INVOKE_DENIED';
    public const AGENT_INVOKE_FAILED = 'AGENT_INVOKE_FAILED';

    private function __construct() {}
}
