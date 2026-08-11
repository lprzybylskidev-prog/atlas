<?php

declare(strict_types=1);

namespace App\Shared\Application\Authorization\DTOs;

final readonly class EffectivePermissionDecision
{
    public function __construct(
        public bool $allowed,
        public string $reason,
    ) {}
}
