<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Exceptions;

final class AgentNameConflictException extends \RuntimeException
{
    public static function for(string $name): self
    {
        return new self(
            "Agent name [{$name}] is already registered. Names must be unique within a single AgentRegistry instance."
        );
    }
}
