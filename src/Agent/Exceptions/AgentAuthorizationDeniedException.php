<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Exceptions;

use RuntimeException;

final class AgentAuthorizationDeniedException extends RuntimeException
{
    public static function for(string $name): self
    {
        return new self(sprintf('Authorization denied for agent [%s].', $name));
    }
}
