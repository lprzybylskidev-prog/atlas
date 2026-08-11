<?php

declare(strict_types=1);

namespace App\Shared\Application\Authorization\Contracts;

interface UserTeamAuthorizationCleaner
{
    public function removeAssignmentsForUserTeam(string $userPublicId, string $teamPublicId): void;
}
