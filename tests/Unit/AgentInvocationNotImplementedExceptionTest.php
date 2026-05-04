<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Exceptions\AgentInvocationNotImplementedException;

it('forRegistry produces a stable message that names Sub-plan B and the docs path', function () {
    $e = AgentInvocationNotImplementedException::forRegistry();

    expect($e)->toBeInstanceOf(\LogicException::class);
    expect($e->getMessage())->toContain('Sub-plan B');
    expect($e->getMessage())->toContain('docs/agents.md');
});
