<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\Contracts;

use App\Modules\Core\Authorization\Application\Public\DTOs\UserTeamAuthorizationAssignments;

interface UserTeamAuthorizationManager
{
    /**
     * @return list<string>
     */
    public function roleOptions(): array;

    /**
     * @return list<string>
     */
    public function permissionOptions(): array;

    /**
     * @return array<string, list<string>>
     */
    public function rolePermissionMap(): array;

    public function assignmentsForUserTeam(string $userPublicId, string $teamPublicId): UserTeamAuthorizationAssignments;

    /**
     * @param  list<string>  $roleNames
     * @param  list<string>  $directPermissionNames
     */
    public function replaceAssignmentsForUserTeam(
        string $actorPublicId,
        string $userPublicId,
        string $teamPublicId,
        array $roleNames,
        array $directPermissionNames,
        ?string $reason = null,
    ): void;
}
