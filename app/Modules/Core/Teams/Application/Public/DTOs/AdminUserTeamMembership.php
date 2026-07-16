<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\DTOs;

final readonly class AdminUserTeamMembership
{
    public function __construct(
        public string $teamPublicId,
        public string $teamName,
        public bool $teamActive,
        public ?string $validFrom,
        public ?string $validTo,
    ) {}
}
