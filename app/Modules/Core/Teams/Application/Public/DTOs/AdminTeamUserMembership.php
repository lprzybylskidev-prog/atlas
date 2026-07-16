<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\DTOs;

final readonly class AdminTeamUserMembership
{
    public function __construct(
        public string $userPublicId,
        public string $userName,
        public string $userEmail,
        public ?string $validFrom,
        public ?string $validTo,
    ) {}
}
