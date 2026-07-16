<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\Contracts;

use App\Modules\Core\Teams\Application\Public\DTOs\AdminTeamUserMembership;
use App\Modules\Core\Teams\Application\Public\DTOs\AdminUserTeamMembership;
use App\Modules\Core\Teams\Application\Public\DTOs\TeamOption;

interface UserTeamMembershipManager
{
    /**
     * @return list<AdminUserTeamMembership>
     */
    public function activeMembershipsForUser(string $userPublicId): array;

    public function hasActiveMembership(string $userPublicId, string $teamPublicId): bool;

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
