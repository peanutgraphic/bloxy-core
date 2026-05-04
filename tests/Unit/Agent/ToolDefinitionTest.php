<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Llm\ToolDefinition;

it('serializes to the Anthropic tool-definition shape', function () {
    $tool = new ToolDefinition(
        name: 'get_weather',
        description: 'Get current weather for a location',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string', 'description' => 'City and state'],
            ],
            'required' => ['location'],
        ],
    );

    expect($tool->toAnthropicArray())->toBe([
        'name' => 'get_weather',
        'description' => 'Get current weather for a location',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string', 'description' => 'City and state'],
            ],
            'required' => ['location'],
        ],
    ]);
});

it('rejects empty names', function () {
    expect(fn () => new ToolDefinition(name: '', description: 'x', inputSchema: ['type' => 'object']))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejects non-object input schemas', function () {
    expect(fn () => new ToolDefinition(name: 'x', description: 'y', inputSchema: ['type' => 'string']))
        ->toThrow(\InvalidArgumentException::class);
});
