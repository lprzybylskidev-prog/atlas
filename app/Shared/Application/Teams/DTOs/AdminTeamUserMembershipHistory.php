<?php

declare(strict_types=1);

namespace App\Shared\Application\Teams\DTOs;

final readonly class AdminTeamUserMembershipHistory
{
    public function __construct(
        public string $userPublicId,
        public string $userName,
        public string $userEmail,
        public ?string $validFrom,
        public ?string $validTo,
        public string $structuralRole,
        public bool $active,
    ) {}
}
