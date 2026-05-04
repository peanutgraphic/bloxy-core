<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Runners;

/**
 * Per-million-token rates for Claude models we ship support for, in
 * USD cents. Anthropic publishes pricing in dollars per 1M tokens; we
 * convert to integer cents so the result fits agent_usage_log.cost_usd_cents
 * without floating-point error.
 *
 * Cache reads are charged at ~0.1x the base input rate; cache writes at
 * 1.25x for the 5-minute TTL (which is what the runner uses by default).
 *
 * Pricing data is cached locally — refresh annually or when Anthropic
 * publishes new rates. Reference: shared/models.md in the claude-api skill.
 */
final class Pricing
{
    /** @var array<string, array{input: int, output: int}> cents per million tokens */
    private const RATES = [
        'claude-opus-4-7' => ['input' => 500, 'output' => 2500],
        'claude-opus-4-6' => ['input' => 500, 'output' => 2500],
        'claude-sonnet-4-6' => ['input' => 300, 'output' => 1500],
        'claude-haiku-4-5' => ['input' => 100, 'output' => 500],
    ];

    private const CACHE_READ_MULTIPLIER = 0.1;
    private const CACHE_WRITE_MULTIPLIER = 1.25;

    public static function costUsdCentsFor(
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cacheReadTokens,
        int $cacheWriteTokens,
    ): ?int {
        $rate = self::RATES[$model] ?? null;
        if ($rate === null) {
            return null;
        }

        $inputCost = ($inputTokens / 1_000_000) * $rate['input'];
        $outputCost = ($outputTokens / 1_000_000) * $rate['output'];
        $cacheReadCost = ($cacheReadTokens / 1_000_000) * $rate['input'] * self::CACHE_READ_MULTIPLIER;
        $cacheWriteCost = ($cacheWriteTokens / 1_000_000) * $rate['input'] * self::CACHE_WRITE_MULTIPLIER;

        return (int) round($inputCost + $outputCost + $cacheReadCost + $cacheWriteCost);
    }

    private function __construct() {}
}
