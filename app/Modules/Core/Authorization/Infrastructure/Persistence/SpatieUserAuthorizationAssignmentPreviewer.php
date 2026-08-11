<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use App\Modules\Core\Authorization\Application\Public\Contracts\UserAuthorizationAssignmentPreviewer;
use App\Modules\Core\Authorization\Application\Public\DTOs\UserAuthorizationPreview;
use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Support\Facades\DB;

final class SpatieUserAuthorizationAssignmentPreviewer implements UserAuthorizationAssignmentPreviewer
{
    public function __construct(
        private readonly UserLookup $users,
        private readonly TeamLookup $teams,
    ) {}

    public function preview(string $userPublicId, string $teamPublicId): UserAuthorizationPreview
    {
        $userId = $this->users->internalIdForPublicId($userPublicId);
        $teamId = $this->teams->internalIdForPublicId($teamPublicId);

        if ($userId === null || $teamId === null) {
            return new UserAuthorizationPreview($userPublicId, [], []);
        }

        $roles = [];

        foreach (DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)
            ->join(AuthorizationDatabaseTable::ROLES, 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', config('auth.providers.users.model'))
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.team_id', $teamId)
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->all() as $roleName) {
            if (is_string($roleName)) {
                $roles[] = $roleName;
            }
        }

        $permissions = [];

        foreach (DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)
            ->join(AuthorizationDatabaseTable::PERMISSIONS, 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_permissions.model_type', config('auth.providers.users.model'))
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.team_id', $teamId)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all() as $permissionName) {
            if (is_string($permissionName)) {
                $permissions[] = $permissionName;
            }
        }

        return new UserAuthorizationPreview($userPublicId, $roles, $permissions);
    }
}
