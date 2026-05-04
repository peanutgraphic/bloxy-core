<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Exceptions;

final class AnthropicContextOverflowException extends AnthropicInvocationException
{
    public static function for(string $name): self
    {
        return new self(sprintf(
            'Agent [%s] exceeded the model context window.',
            $name,
        ));
    }
}
