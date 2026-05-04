<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Exceptions;

use RuntimeException;
use Throwable;

final class AgentInvocationFailedException extends RuntimeException
{
    public static function wrap(string $name, Throwable $original): self
    {
        return new self(
            sprintf('Agent [%s] threw during invocation: %s', $name, $original->getMessage()),
            0,
            $original,
        );
    }
}
