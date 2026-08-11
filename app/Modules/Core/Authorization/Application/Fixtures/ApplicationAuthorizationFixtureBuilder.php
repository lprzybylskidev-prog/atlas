<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Fixtures;

use App\Modules\Core\Authorization\Application\Public\Contracts\AuthorizationFixtureBuilder;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Shared\Application\Authorization\Contracts\UserTeamAuthorizationManager;

final readonly class ApplicationAuthorizationFixtureBuilder implements AuthorizationFixtureBuilder
{
    public function __construct(private UserTeamAuthorizationManager $authorization) {}

    public function assignWorkspaceAccess(string $actorPublicId, string $userPublicId, string $teamPublicId): void
    {
        $roleNames = [StarterRoleName::WorkspaceAccess->value];
        $current = $this->authorization->assignmentsForUserTeam($userPublicId, $teamPublicId);

        if ($current->roleNames === $roleNames && $current->directPermissionNames === []) {
            return;
        }

        $this->authorization->replaceAssignmentsForUserTeam(
            actorPublicId: $actorPublicId,
            userPublicId: $userPublicId,
            teamPublicId: $teamPublicId,
            roleNames: $roleNames,
            directPermissionNames: [],
            reason: 'E2E visibility fixture.',
            sourceType: 'manual',
        );
    }
}
