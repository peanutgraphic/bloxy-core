<?php

declare(strict_types=1);

namespace Bloxy\Core\Audit;

final readonly class VerifyResult
{
    public function __construct(
        public bool $passed,
        public int $checked,
        public ?int $brokenAtId = null,
        public ?string $reason = null,
    ) {
    }
}
