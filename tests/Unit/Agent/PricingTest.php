<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Runners\Pricing;

it('computes Opus 4.7 cost in cents from input + output tokens', function () {
    // claude-opus-4-7: $5/M input, $25/M output.
    expect(Pricing::costUsdCentsFor('claude-opus-4-7', inputTokens: 1_000_000, outputTokens: 0, cacheReadTokens: 0, cacheWriteTokens: 0))
        ->toBe(500);
    expect(Pricing::costUsdCentsFor('claude-opus-4-7', inputTokens: 0, outputTokens: 1_000_000, cacheReadTokens: 0, cacheWriteTokens: 0))
        ->toBe(2500);
});

it('discounts cache reads (~0.1x base) and surcharges cache writes (~1.25x base)', function () {
    expect(Pricing::costUsdCentsFor('claude-opus-4-7', inputTokens: 0, outputTokens: 0, cacheReadTokens: 1_000_000, cacheWriteTokens: 0))
        ->toBe(50);
    expect(Pricing::costUsdCentsFor('claude-opus-4-7', inputTokens: 0, outputTokens: 0, cacheReadTokens: 0, cacheWriteTokens: 1_000_000))
        ->toBe(625);
});

it('returns null for an unknown model id (no silent miscalculation)', function () {
    expect(Pricing::costUsdCentsFor('mystery-model', inputTokens: 100, outputTokens: 100, cacheReadTokens: 0, cacheWriteTokens: 0))
        ->toBeNull();
});
