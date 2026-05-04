<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Llm;

use InvalidArgumentException;

/**
 * Schema for a single tool an LlmAgent exposes to the model.
 *
 * The runner serializes this via toAnthropicArray() into Anthropic's
 * tool_use shape; other runners (a future OpenAI runner, etc.) translate
 * from the same value object.
 */
final class ToolDefinition
{
    /**
     * @param array<string, mixed> $inputSchema A JSON Schema describing the tool's params.
     *                                          Must have type=object at the top level.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('ToolDefinition name must be non-empty.');
        }
        if (($inputSchema['type'] ?? null) !== 'object') {
            throw new InvalidArgumentException(
                "ToolDefinition [{$name}] inputSchema must have top-level type=object.",
            );
        }
    }

    /** @return array{name: string, description: string, input_schema: array<string, mixed>} */
    public function toAnthropicArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $this->inputSchema,
        ];
    }
}
