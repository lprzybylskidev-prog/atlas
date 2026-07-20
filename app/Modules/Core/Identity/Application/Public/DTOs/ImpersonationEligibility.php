<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\DTOs;

final readonly class ImpersonationEligibility
{
    public function __construct(
        public bool $canStart,
        public bool $requiresSensitiveOverride = false,
        public ?string $blockedReason = null,
    ) {}
}
