<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Exceptions\AgentAuthorizationDeniedException;
use Bloxy\Core\Agent\Exceptions\AgentInvocationFailedException;
use Bloxy\Core\Agent\Exceptions\AgentNotFoundException;

it('AgentNotFoundException::for produces a stable message naming the agent', function () {
    $e = AgentNotFoundException::for('tracy.summarize');
    expect($e->getMessage())->toContain('tracy.summarize');
});

it('AgentAuthorizationDeniedException::for produces a stable message naming the agent', function () {
    $e = AgentAuthorizationDeniedException::for('tracy.summarize');
    expect($e->getMessage())->toContain('tracy.summarize');
});

it('AgentInvocationFailedException::wrap preserves the original exception as previous', function () {
    $original = new RuntimeException('boom');
    $wrapped = AgentInvocationFailedException::wrap('tracy.summarize', $original);
    expect($wrapped->getPrevious())->toBe($original);
    expect($wrapped->getMessage())->toContain('tracy.summarize');
});

it('AgentAuditActions exposes the three string constants', function () {
    expect(\Bloxy\Core\Agent\Audit\AgentAuditActions::AGENT_INVOKE)->toBe('AGENT_INVOKE');
    expect(\Bloxy\Core\Agent\Audit\AgentAuditActions::AGENT_INVOKE_DENIED)->toBe('AGENT_INVOKE_DENIED');
    expect(\Bloxy\Core\Agent\Audit\AgentAuditActions::AGENT_INVOKE_FAILED)->toBe('AGENT_INVOKE_FAILED');
});
