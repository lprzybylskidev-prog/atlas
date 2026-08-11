<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Shared\Application\Authorization\Contracts\EffectivePermissionChecker;
use App\Shared\Application\Authorization\DTOs\EffectivePermissionDecision;
use App\Shared\Application\Authorization\DTOs\EffectivePermissionRequest;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\ModuleKeyResolver;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use Illuminate\Support\Facades\DB;

final class SpatieEffectivePermissionChecker implements EffectivePermissionChecker
{
    public function __construct(
        private readonly ModuleActivationService $activation,
        private readonly ModuleKeyResolver $moduleKeys,
        private readonly UserLookup $users,
        private readonly TeamLookup $teams,
        private readonly UserTeamMembershipManager $memberships,
    ) {}

    public function check(EffectivePermissionRequest $request): EffectivePermissionDecision
    {
        if ($request->teamPublicId === null) {
            return $this->deny('authorization.active_team_required');
        }

        $permission = DB::table(AuthorizationDatabaseTable::PERMISSIONS)
            ->where('name', $request->permission)
            ->where('guard_name', 'web')
            ->first(['id']);

        if ($permission === null || ! property_exists($permission, 'id') || ! is_int($permission->id)) {
            return $this->deny('authorization.permission_unknown');
        }

        $userId = $this->users->internalIdForPublicId($request->userPublicId);

        if ($userId === null) {
            return $this->deny('authorization.user_unknown');
        }

        $teamId = $this->teams->activeInternalIdForPublicId($request->teamPublicId);

        if ($teamId === null) {
            return $this->deny('authorization.active_team_invalid');
        }

        $moduleState = $this->activation->effectiveState($this->moduleKeys->forPermission($request->permission), $teamId);

        if (! $moduleState->effectiveEnabled) {
            return $this->deny('authorization.module_inactive');
        }

        if (! $this->memberships->hasActiveMembership($request->userPublicId, $request->teamPublicId)) {
            return $this->deny('authorization.active_team_not_assigned');
        }

        if ($this->hasDirectPermission($userId, $teamId, $permission->id)) {
            return $this->allow();
        }

        if ($this->hasRolePermission($userId, $teamId, $permission->id)) {
            return $this->allow();
        }

        return $this->deny('authorization.permission_missing');
    }

    private function hasDirectPermission(int $userId, int $teamId, int $permissionId): bool
    {
        return DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)
            ->where('permission_id', $permissionId)
            ->where('team_id', $teamId)
            ->where('model_id', $userId)
            ->where('model_type', $this->userModelType())
            ->exists();
    }

    private function hasRolePermission(int $userId, int $teamId, int $permissionId): bool
    {
        return DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)
            ->join(AuthorizationDatabaseTable::ROLE_HAS_PERMISSIONS, 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
            ->where('role_has_permissions.permission_id', $permissionId)
            ->where('model_has_roles.team_id', $teamId)
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', $this->userModelType())
            ->exists();
    }

    private function userModelType(): string
    {
        $model = config('auth.providers.users.model');

        return is_string($model) && $model !== '' ? $model : 'App\\Modules\\Core\\Identity\\Infrastructure\\Persistence\\User';
    }

    private function allow(): EffectivePermissionDecision
    {
        return new EffectivePermissionDecision(true, 'authorization.allowed');
    }

    private function deny(string $reason): EffectivePermissionDecision
    {
        return new EffectivePermissionDecision(false, $reason);
    }
}
