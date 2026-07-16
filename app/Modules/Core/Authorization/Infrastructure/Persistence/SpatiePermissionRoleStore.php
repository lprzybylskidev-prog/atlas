<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class SpatiePermissionRoleStore implements PermissionRoleStore
{
    public function ensurePermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission->name,
                'guard_name' => 'web',
            ], [
                'public_id' => (string) Str::ulid(),
            ]);
        }
    }

    public function roleExists(string $roleName): bool
    {
        return Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->whereNull(config()->string('permission.column_names.team_foreign_key'))
            ->exists();
    }

    public function createRoleWithPermissions(string $roleName, array $permissionNames): void
    {
        $role = Role::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => $roleName,
            'guard_name' => 'web',
            config()->string('permission.column_names.team_foreign_key') => null,
        ]);

        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->get();

        $role->givePermissionTo($permissions);
    }

    public function rolePermissionNames(string $roleName): array
    {
        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->first();

        if (! $role instanceof Role) {
            return [];
        }

        return array_values(array_filter($role->permissions()
            ->orderBy('name')
            ->pluck('name')
            ->all(), 'is_string'));
    }

    public function grantPermissionsToRole(string $roleName, array $permissionNames): void
    {
        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->firstOrFail();

        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->get();

        $role->givePermissionTo($permissions);
    }

    public function anyUserHasRole(string $roleName): bool
    {
        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->first();

        if (! $role instanceof Role) {
            return false;
        }

        return DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->exists();
    }

    public function assignRoleToUserInTeam(string $userPublicId, string $teamPublicId, string $roleName): void
    {
        $userId = $this->userId($userPublicId);
        $teamId = $this->teamId($teamPublicId);
        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->firstOrFail();

        if (! is_int($userId) || ! is_int($teamId)) {
            return;
        }

        DB::table('team_user_assignments')->updateOrInsert([
            'team_id' => $teamId,
            'user_id' => $userId,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->updateOrInsert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $userId,
            'team_id' => $teamId,
        ]);
    }

    public function assignPermissionsToUserInTeam(string $userPublicId, string $teamPublicId, array $permissionNames): void
    {
        $userId = $this->userId($userPublicId);
        $teamId = $this->teamId($teamPublicId);

        if (! is_int($userId) || ! is_int($teamId)) {
            return;
        }

        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->get();

        foreach ($permissions as $permission) {
            DB::table('model_has_permissions')->updateOrInsert([
                'permission_id' => $permission->id,
                'model_type' => config('auth.providers.users.model'),
                'model_id' => $userId,
                'team_id' => $teamId,
            ]);
        }
    }

    public function userHasOnboardingPackage(string $userPublicId): bool
    {
        $userId = $this->userId($userPublicId);

        return is_int($userId) && DB::table('user_onboarding_packages')->where('user_id', $userId)->exists();
    }

    public function recordUserOnboardingPackage(string $userPublicId, string $teamPublicId, string $packageName): void
    {
        $userId = $this->userId($userPublicId);
        $teamId = $this->teamId($teamPublicId);

        if (! is_int($userId) || ! is_int($teamId)) {
            return;
        }

        DB::table('user_onboarding_packages')->insert([
            'user_id' => $userId,
            'team_id' => $teamId,
            'package_name' => $packageName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function copyAssignmentsBetweenUsers(string $sourceUserPublicId, string $targetUserPublicId, string $teamPublicId): void
    {
        $sourceUserId = $this->userId($sourceUserPublicId);
        $targetUserId = $this->userId($targetUserPublicId);
        $teamId = $this->teamId($teamPublicId);

        if (! is_int($sourceUserId) || ! is_int($targetUserId) || ! is_int($teamId)) {
            return;
        }

        DB::table('team_user_assignments')->updateOrInsert([
            'team_id' => $teamId,
            'user_id' => $targetUserId,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table('model_has_roles')->where([
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $sourceUserId,
            'team_id' => $teamId,
        ])->get(['role_id']) as $role) {
            $roleId = get_object_vars($role)['role_id'] ?? null;

            if (is_int($roleId)) {
                DB::table('model_has_roles')->updateOrInsert([
                    'role_id' => $roleId,
                    'model_type' => config('auth.providers.users.model'),
                    'model_id' => $targetUserId,
                    'team_id' => $teamId,
                ]);
            }
        }

        foreach (DB::table('model_has_permissions')->where([
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $sourceUserId,
            'team_id' => $teamId,
        ])->get(['permission_id']) as $permission) {
            $permissionId = get_object_vars($permission)['permission_id'] ?? null;

            if (is_int($permissionId)) {
                DB::table('model_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'model_type' => config('auth.providers.users.model'),
                    'model_id' => $targetUserId,
                    'team_id' => $teamId,
                ]);
            }
        }
    }

    private function userId(string $userPublicId): mixed
    {
        return DB::table('users')->where('public_id', $userPublicId)->value('id');
    }

    private function teamId(string $teamPublicId): mixed
    {
        return DB::table('teams')->where('public_id', $teamPublicId)->value('id');
    }
}
