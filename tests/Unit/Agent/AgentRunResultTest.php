<?php

declare(strict_types=1);

use Bloxy\Core\Agent\AgentRunResult;

it('exposes the result array and null token meta by default', function () {
    $r = new AgentRunResult(['hello' => 'world']);
    expect($r->result)->toBe(['hello' => 'world']);
    expect($r->promptTokens)->toBeNull();
    expect($r->completionTokens)->toBeNull();
    expect($r->costUsdCents)->toBeNull();
});

it('carries token + cost meta when supplied', function () {
    $r = new AgentRunResult(
        result: ['ok' => true],
        promptTokens: 1234,
        completionTokens: 567,
        costUsdCents: 21,
    );
    expect($r->promptTokens)->toBe(1234);
    expect($r->completionTokens)->toBe(567);
    expect($r->costUsdCents)->toBe(21);
});
