<?php

declare(strict_types=1);

namespace App\Shared\Application\Teams\DTOs;

final readonly class TeamLookupSummary
{
    public function __construct(
        public int $internalId,
        public string $publicId,
        public string $name,
        public bool $active,
    ) {}
}
