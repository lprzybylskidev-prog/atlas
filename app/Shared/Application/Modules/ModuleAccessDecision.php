<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

final readonly class ModuleAccessDecision
{
    private function __construct(
        public bool $allowed,
        public ?ModuleAccessDenialReason $denialReason,
    ) {}

    public static function allow(): self
    {
        return new self(true, null);
    }

    public static function deny(ModuleAccessDenialReason $reason): self
    {
        return new self(false, $reason);
    }
}
