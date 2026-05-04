<?php

declare(strict_types=1);

it('exposes default Anthropic config values via bloxy.agent.anthropic.*', function () {
    expect(config('bloxy.agent.anthropic.model'))->toBe('claude-opus-4-7');
    expect(config('bloxy.agent.anthropic.max_tokens'))->toBe(4096);
    expect(config('bloxy.agent.anthropic.effort'))->toBe('high');
    expect(config('bloxy.agent.anthropic.thinking'))->toBe('adaptive');
    expect(config('bloxy.agent.anthropic.prompt_cache_enabled'))->toBeTrue();
});

it('reads BLOXY_ANTHROPIC_API_KEY from env when present', function () {
    putenv('BLOXY_ANTHROPIC_API_KEY=sk-test-12345');
    config()->set('bloxy.agent.anthropic.api_key', env('BLOXY_ANTHROPIC_API_KEY'));
    expect(config('bloxy.agent.anthropic.api_key'))->toBe('sk-test-12345');
    putenv('BLOXY_ANTHROPIC_API_KEY');
});
