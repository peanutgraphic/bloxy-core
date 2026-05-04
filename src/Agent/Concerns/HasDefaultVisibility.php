<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\Concerns;

/**
 * Default implementation of Agent::visibleIn().
 *
 * Agents that should appear in both surfaces use this trait. Agents that
 * want to scope themselves to one surface implement visibleIn() directly.
 */
trait HasDefaultVisibility
{
    /** @return array<string> */
    public function visibleIn(): array
    {
        return ['cockpit', 'portal'];
    }
}
