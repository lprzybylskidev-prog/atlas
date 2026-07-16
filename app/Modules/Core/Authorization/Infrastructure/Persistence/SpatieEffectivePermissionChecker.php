<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionDecision;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class SpatieEffectivePermissionChecker implements EffectivePermissionChecker
{
    public function check(EffectivePermissionRequest $request): EffectivePermissionDecision
    {
        if ($request->teamPublicId === null) {
            return $this->deny('authorization.active_team_required');
        }

        $permission = DB::table('permissions')
            ->where('name', $request->permission)
            ->where('guard_name', 'web')
            ->first(['id']);

        if ($permission === null || ! property_exists($permission, 'id') || ! is_int($permission->id)) {
            return $this->deny('authorization.permission_unknown');
        }

        $user = DB::table('users')
            ->where('public_id', $request->userPublicId)
            ->first(['id']);

        if ($user === null || ! property_exists($user, 'id') || ! is_int($user->id)) {
            return $this->deny('authorization.user_unknown');
        }

        $team = DB::table('teams')
            ->where('public_id', $request->teamPublicId)
            ->where('is_active', true)
            ->first(['id']);

        if ($team === null || ! property_exists($team, 'id') || ! is_int($team->id)) {
            return $this->deny('authorization.active_team_invalid');
        }

        if (! $this->userBelongsToTeam($user->id, $team->id)) {
            return $this->deny('authorization.active_team_not_assigned');
        }

        if ($this->hasDirectPermission($user->id, $team->id, $permission->id)) {
            return $this->allow();
        }

        if ($this->hasRolePermission($user->id, $team->id, $permission->id)) {
            return $this->allow();
        }

        return $this->deny('authorization.permission_missing');
    }

    private function hasDirectPermission(int $userId, int $teamId, int $permissionId): bool
    {
        return DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('team_id', $teamId)
            ->where('model_id', $userId)
            ->where('model_type', $this->userModelType())
            ->exists();
    }

    private function hasRolePermission(int $userId, int $teamId, int $permissionId): bool
    {
        return DB::table('model_has_roles')
            ->join('role_has_permissions', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
            ->where('role_has_permissions.permission_id', $permissionId)
            ->where('model_has_roles.team_id', $teamId)
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', $this->userModelType())
            ->exists();
    }

    private function userBelongsToTeam(int $userId, int $teamId): bool
    {
        return DB::table('team_user_assignments')
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->where(static function (Builder $query): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>', now());
            })
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
