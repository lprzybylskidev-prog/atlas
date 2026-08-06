<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use App\Modules\Core\Authorization\Application\Public\Contracts\UserAuthorizationAssignmentPreviewer;
use App\Modules\Core\Authorization\Application\Public\DTOs\UserAuthorizationPreview;
use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use Illuminate\Support\Facades\DB;

final class SpatieUserAuthorizationAssignmentPreviewer implements UserAuthorizationAssignmentPreviewer
{
    public function preview(string $userPublicId, string $teamPublicId): UserAuthorizationPreview
    {
        $userId = DB::table(IdentityDatabaseTable::USERS)->where('public_id', $userPublicId)->value('id');
        $teamId = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if (! is_int($userId) || ! is_int($teamId)) {
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
