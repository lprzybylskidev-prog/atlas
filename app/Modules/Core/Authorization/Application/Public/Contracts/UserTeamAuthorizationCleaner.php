<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\Contracts;

interface UserTeamAuthorizationCleaner
{
    public function removeAssignmentsForUserTeam(string $userPublicId, string $teamPublicId): void;
}
