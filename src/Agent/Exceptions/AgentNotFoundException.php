<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Exceptions;

use RuntimeException;

final class AgentNotFoundException extends RuntimeException
{
    public static function for(string $name): self
    {
        return new self(sprintf('No agent registered with name [%s].', $name));
    }
}
