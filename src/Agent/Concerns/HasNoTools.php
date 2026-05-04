<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Concerns;

use InvalidArgumentException;

/**
 * Convenience for LlmAgent implementations that don't expose tools.
 *
 * Provides empty tools() and a handleTool() that throws — a tools-free
 * agent should never receive a tool call, so reaching it indicates a model
 * misconfiguration. Failing loud surfaces it in tests.
 */
trait HasNoTools
{
    /** @return array<\Bloxy\Core\Agent\Llm\ToolDefinition> */
    public function tools(): array
    {
        return [];
    }

    public function handleTool(string $name, array $input): array|string
    {
        throw new InvalidArgumentException(
            sprintf('Agent declares no tools but received a call to [%s].', $name),
        );
    }
}
