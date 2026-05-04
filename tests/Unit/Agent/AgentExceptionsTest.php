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

it('AnthropicInvocationException factory captures the response stop_reason', function () {
    $e = \Bloxy\Core\Agent\Exceptions\AnthropicInvocationException::forStopReason('demo.x', 'unknown_reason');
    expect($e->getMessage())->toContain('demo.x');
    expect($e->getMessage())->toContain('unknown_reason');
});

it('AnthropicRefusalException carries the model-stated reason', function () {
    $e = \Bloxy\Core\Agent\Exceptions\AnthropicRefusalException::for('demo.x', 'refused due to safety policy');
    expect($e->getMessage())->toContain('demo.x');
    expect($e->getMessage())->toContain('refused due to safety policy');
});

it('AnthropicMaxTokensException notes hitting the cap', function () {
    $e = \Bloxy\Core\Agent\Exceptions\AnthropicMaxTokensException::for('demo.x', 4096);
    expect($e->getMessage())->toContain('demo.x');
    expect($e->getMessage())->toContain('4096');
});

it('AnthropicContextOverflowException notes the context window was exceeded', function () {
    $e = \Bloxy\Core\Agent\Exceptions\AnthropicContextOverflowException::for('demo.x');
    expect($e->getMessage())->toContain('demo.x');
});
