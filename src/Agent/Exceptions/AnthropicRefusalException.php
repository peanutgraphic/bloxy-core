<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Exceptions;

final class AnthropicRefusalException extends AnthropicInvocationException
{
    public static function for(string $name, string $modelStatedReason): self
    {
        return new self(sprintf(
            'Agent [%s] was refused by the model: %s',
            $name,
            $modelStatedReason,
        ));
    }
}
