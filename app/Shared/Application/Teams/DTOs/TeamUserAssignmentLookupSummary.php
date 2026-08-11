<?php

declare(strict_types=1);

namespace App\Shared\Application\Teams\DTOs;

final readonly class TeamUserAssignmentLookupSummary
{
    public function __construct(
        public int $assignmentId,
        public int $userId,
        public string $userPublicId,
        public string $userName,
        public string $userEmail,
        public int $teamId,
        public string $teamPublicId,
        public string $teamName,
    ) {}
}
