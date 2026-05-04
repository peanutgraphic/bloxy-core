<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Exceptions;

final class AnthropicMaxTokensException extends AnthropicInvocationException
{
    public static function for(string $name, int $maxTokens): self
    {
        return new self(sprintf(
            'Agent [%s] hit max_tokens=%d before completing.',
            $name,
            $maxTokens,
        ));
    }
}
