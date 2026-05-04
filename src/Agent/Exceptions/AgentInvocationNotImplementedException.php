<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Exceptions;

final class AgentInvocationNotImplementedException extends \LogicException
{
    public static function forRegistry(): self
    {
        return new self(
            'AgentRegistry::invoke is not implemented in M2 Sub-plan A. '
            . 'Sub-plan B will define the dispatch semantics. See '
            . 'docs/agents.md for the current contract.'
        );
    }
}
