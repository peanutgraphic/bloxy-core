<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Exceptions;

use RuntimeException;

class AnthropicInvocationException extends RuntimeException
{
    public static function forStopReason(string $name, string $stopReason): self
    {
        return new self(sprintf(
            'Agent [%s] received unhandled stop_reason [%s] from Anthropic.',
            $name,
            $stopReason,
        ));
    }
}
