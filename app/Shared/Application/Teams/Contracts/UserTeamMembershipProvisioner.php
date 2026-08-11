<?php

declare(strict_types=1);

namespace App\Shared\Application\Teams\Contracts;

interface UserTeamMembershipProvisioner
{
    public function ensureUserTeamMembership(string $userPublicId, string $teamPublicId): void;
}
