<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Runners;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;

final class AnthropicConfig
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $model = 'claude-opus-4-7',
        public readonly int $maxTokens = 4096,
        public readonly string $effort = 'high',
        /** 'adaptive' | 'disabled' | 'summarized' */
        public readonly string $thinking = 'adaptive',
        public readonly bool $promptCacheEnabled = true,
    ) {
        if ($apiKey === '') {
            throw new InvalidArgumentException(
                'AnthropicConfig requires a non-empty api key. '
                . 'Set BLOXY_ANTHROPIC_API_KEY or bind a custom AnthropicConfig.',
            );
        }
        if ($maxTokens < 1) {
            throw new InvalidArgumentException('maxTokens must be >= 1.');
        }
        if (! in_array($thinking, ['adaptive', 'disabled', 'summarized'], true)) {
            throw new InvalidArgumentException("Invalid thinking mode [{$thinking}].");
        }
    }

    public static function fromConfig(ConfigRepository $config): self
    {
        $a = $config->get('bloxy.agent.anthropic', []);
        return new self(
            apiKey: (string) ($a['api_key'] ?? ''),
            model: (string) ($a['model'] ?? 'claude-opus-4-7'),
            maxTokens: (int) ($a['max_tokens'] ?? 4096),
            effort: (string) ($a['effort'] ?? 'high'),
            thinking: (string) ($a['thinking'] ?? 'adaptive'),
            promptCacheEnabled: (bool) ($a['prompt_cache_enabled'] ?? true),
        );
    }
}
