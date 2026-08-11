<?php

declare(strict_types=1);

namespace App\Shared\Application\Teams\Contracts;

use App\Shared\Application\Teams\DTOs\AdminTeamUserMembership;
use App\Shared\Application\Teams\DTOs\AdminUserTeamMembership;
use App\Shared\Application\Teams\DTOs\TeamOption;

interface UserTeamMembershipManager
{
    /**
     * @return list<AdminUserTeamMembership>
     */
    public function activeMembershipsForUser(string $userPublicId): array;

    public function hasActiveMembership(string $userPublicId, string $teamPublicId): bool;

    public function teamExists(string $teamPublicId): bool;

    /**
     * @return list<AdminTeamUserMembership>
     */
    public function activeMembershipsForTeam(string $teamPublicId): array;

    /**
     * @return list<array{value: string, label: string}>
     */
    public function assignableUsersForTeam(string $teamPublicId): array;

    /**
     * @return list<TeamOption>
     */
    public function activeTeamOptions(): array;

    /**
     * @return list<TeamOption>
     */
    public function assignableTeamsForUser(string $userPublicId): array;

    public function addAccess(string $actorPublicId, string $userPublicId, string $teamPublicId): void;

    public function removeAccess(string $actorPublicId, string $userPublicId, string $teamPublicId, string $reason): void;
}
