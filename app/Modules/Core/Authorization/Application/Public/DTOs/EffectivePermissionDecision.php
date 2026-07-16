<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\DTOs;

final readonly class EffectivePermissionDecision
{
    public function __construct(
        public bool $allowed,
        public string $reason,
    ) {}
}
