<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationCleaner;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use App\Modules\Core\Authorization\Application\Public\DTOs\UserTeamAuthorizationAssignments;
use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class SpatiePermissionRoleStore implements PermissionRoleStore, UserTeamAuthorizationCleaner, UserTeamAuthorizationManager
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PermissionCatalogRegistry $permissionCatalog,
    ) {}

    public function ensurePermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $record = Permission::query()->firstOrCreate([
                'name' => $permission->name,
                'guard_name' => 'web',
            ], [
                'public_id' => (string) Str::ulid(),
                'display_name' => $permission->displayName ?? $this->humanizeName($permission->name),
            ]);

            if (! is_string($record->getAttribute('display_name')) || $record->getAttribute('display_name') === '' || $record->getAttribute('display_name') === $permission->name) {
                $record->forceFill(['display_name' => $permission->displayName ?? $this->humanizeName($permission->name)])->save();
            }
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
            'display_name' => $this->humanizeName($roleName),
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

        return DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)
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

        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->updateOrInsert([
            'team_id' => $teamId,
            'user_id' => $userId,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->updateOrInsert([
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
            DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)->updateOrInsert([
                'permission_id' => $permission->id,
                'model_type' => config('auth.providers.users.model'),
                'model_id' => $userId,
                'team_id' => $teamId,
            ]);
        }
    }

    public function userHasOnboardingPackage(string $userPublicId, string $teamPublicId, string $packageName): bool
    {
        $userId = $this->userId($userPublicId);
        $teamId = $this->teamId($teamPublicId);

        return is_int($userId) && is_int($teamId) && DB::table(AuthorizationDatabaseTable::USER_ONBOARDING_PACKAGES)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->exists();
    }

    public function recordUserOnboardingPackage(string $userPublicId, string $teamPublicId, string $packageName): void
    {
        $userId = $this->userId($userPublicId);
        $teamId = $this->teamId($teamPublicId);

        if (! is_int($userId) || ! is_int($teamId)) {
            return;
        }

        DB::table(AuthorizationDatabaseTable::USER_ONBOARDING_PACKAGES)->insert([
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

        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->updateOrInsert([
            'team_id' => $teamId,
            'user_id' => $targetUserId,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->where([
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $sourceUserId,
            'team_id' => $teamId,
        ])->get(['role_id']) as $role) {
            $roleId = get_object_vars($role)['role_id'] ?? null;

            if (is_int($roleId)) {
                DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->updateOrInsert([
                    'role_id' => $roleId,
                    'model_type' => config('auth.providers.users.model'),
                    'model_id' => $targetUserId,
                    'team_id' => $teamId,
                ]);
            }
        }

        foreach (DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)->where([
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $sourceUserId,
            'team_id' => $teamId,
        ])->get(['permission_id']) as $permission) {
            $permissionId = get_object_vars($permission)['permission_id'] ?? null;

            if (is_int($permissionId)) {
                DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)->updateOrInsert([
                    'permission_id' => $permissionId,
                    'model_type' => config('auth.providers.users.model'),
                    'model_id' => $targetUserId,
                    'team_id' => $teamId,
                ]);
            }
        }
    }

    public function removeAssignmentsForUserTeam(string $userPublicId, string $teamPublicId): void
    {
        $userId = $this->userId($userPublicId);
        $teamId = $this->teamId($teamPublicId);

        if (! is_int($userId) || ! is_int($teamId)) {
            return;
        }

        DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)
            ->where('model_type', config('auth.providers.users.model'))
            ->where('model_id', $userId)
            ->where('team_id', $teamId)
            ->delete();

        DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)
            ->where('model_type', config('auth.providers.users.model'))
            ->where('model_id', $userId)
            ->where('team_id', $teamId)
            ->delete();
    }

    public function roleOptions(): array
    {
        return array_values(Role::query()
            ->where('guard_name', 'web')
            ->whereNull(config()->string('permission.column_names.team_foreign_key'))
            ->orderBy('display_name')
            ->orderBy('name')
            ->get(['name', 'display_name'])
            ->map(static function (Role $role): array {
                $nameValue = $role->getAttribute('name');
                $displayNameValue = $role->getAttribute('display_name');
                $name = is_string($nameValue) ? $nameValue : '';
                $displayName = is_string($displayNameValue) && $displayNameValue !== '' && $displayNameValue !== $name
                    ? $displayNameValue
                    : str($name)->replace(['.', '-', '_'], ' ')->headline()->toString();

                return ['value' => $name, 'label' => $displayName];
            })
            ->filter(static fn (array $option): bool => $option['value'] !== '')
            ->values()
            ->all());
    }

    public function permissionOptions(): array
    {
        $storedLabels = DB::table(AuthorizationDatabaseTable::PERMISSIONS)
            ->pluck('display_name', 'name')
            ->filter(static fn (mixed $label, mixed $name): bool => is_string($name) && is_string($label) && $label !== '');

        return array_map(function ($permission) use ($storedLabels): array {
            $stored = $storedLabels->get($permission->name);

            return [
                'value' => $permission->name,
                'label' => is_string($stored) && $stored !== $permission->name
                    ? $stored
                    : ($permission->displayName ?? $this->humanizeName($permission->name)),
            ];
        }, $this->permissionCatalog->all());
    }

    public function rolePermissionMap(): array
    {
        $map = [];

        foreach ($this->roleOptionValues() as $roleName) {
            $map[$roleName] = $this->rolePermissionNames($roleName);
        }

        return $map;
    }

    public function assignmentsForUserTeam(string $userPublicId, string $teamPublicId): UserTeamAuthorizationAssignments
    {
        $userId = $this->userId($userPublicId);
        $teamId = $this->teamId($teamPublicId);

        if (! is_int($userId) || ! is_int($teamId)) {
            return new UserTeamAuthorizationAssignments($userPublicId, $teamPublicId, [], []);
        }

        $roles = array_values(array_filter(DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)
            ->join(AuthorizationDatabaseTable::ROLES, 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', config('auth.providers.users.model'))
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.team_id', $teamId)
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->all(), 'is_string'));

        $permissions = array_values(array_filter(DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)
            ->join(AuthorizationDatabaseTable::PERMISSIONS, 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_permissions.model_type', config('auth.providers.users.model'))
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.team_id', $teamId)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all(), 'is_string'));

        return new UserTeamAuthorizationAssignments($userPublicId, $teamPublicId, $roles, $permissions);
    }

    public function replaceAssignmentsForUserTeam(
        string $actorPublicId,
        string $userPublicId,
        string $teamPublicId,
        array $roleNames,
        array $directPermissionNames,
        ?string $reason = null,
    ): void {
        $userId = $this->userId($userPublicId);
        $teamId = $this->teamId($teamPublicId);

        if (! is_int($userId) || ! is_int($teamId)) {
            return;
        }

        $before = $this->assignmentsForUserTeam($userPublicId, $teamPublicId);
        $roleNames = $this->validRoleNames($roleNames);
        $directPermissionNames = $this->validPermissionNames($directPermissionNames);

        DB::transaction(function () use ($userId, $teamId, $roleNames, $directPermissionNames): void {
            DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)
                ->where('model_type', config('auth.providers.users.model'))
                ->where('model_id', $userId)
                ->where('team_id', $teamId)
                ->delete();

            DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)
                ->where('model_type', config('auth.providers.users.model'))
                ->where('model_id', $userId)
                ->where('team_id', $teamId)
                ->delete();

            foreach (Role::query()->whereIn('name', $roleNames)->where('guard_name', 'web')->get(['id']) as $role) {
                DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->insert([
                    'role_id' => $role->id,
                    'model_type' => config('auth.providers.users.model'),
                    'model_id' => $userId,
                    'team_id' => $teamId,
                ]);
            }

            foreach (Permission::query()->whereIn('name', $directPermissionNames)->where('guard_name', 'web')->get(['id']) as $permission) {
                DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)->insert([
                    'permission_id' => $permission->id,
                    'model_type' => config('auth.providers.users.model'),
                    'model_id' => $userId,
                    'team_id' => $teamId,
                ]);
            }
        });

        $this->audit->record(new AuditEvent(
            module: 'authorization',
            action: 'authorization.user_team_assignments_replaced',
            result: 'succeeded',
            source: 'admin',
            actorPublicId: $actorPublicId,
            targetType: 'user',
            targetPublicId: $userPublicId,
            teamPublicId: $teamPublicId,
            before: [
                'roles' => $before->roleNames,
                'direct_permissions' => $before->directPermissionNames,
            ],
            after: [
                'roles' => $roleNames,
                'direct_permissions' => $directPermissionNames,
                'reason' => $reason,
            ],
            security: true,
            securityCategory: SecurityAuditCategory::Authorization,
        ));
    }

    /**
     * @param  list<string>  $roleNames
     * @return list<string>
     */
    private function validRoleNames(array $roleNames): array
    {
        $available = $this->roleOptionValues();

        return array_values(array_intersect(array_values(array_unique($roleNames)), $available));
    }

    /**
     * @param  list<string>  $permissionNames
     * @return list<string>
     */
    private function validPermissionNames(array $permissionNames): array
    {
        $available = $this->permissionOptionValues();

        return array_values(array_intersect(array_values(array_unique($permissionNames)), $available));
    }

    /**
     * @return list<string>
     */
    private function roleOptionValues(): array
    {
        return array_map(static fn (array $option): string => $option['value'], $this->roleOptions());
    }

    /**
     * @return list<string>
     */
    private function permissionOptionValues(): array
    {
        return array_map(static fn (array $option): string => $option['value'], $this->permissionOptions());
    }

    private function humanizeName(string $name): string
    {
        return str($name)->replace(['.', '-', '_'], ' ')->headline()->toString();
    }

    private function userId(string $userPublicId): mixed
    {
        return DB::table(IdentityDatabaseTable::USERS)->where('public_id', $userPublicId)->value('id');
    }

    private function teamId(string $teamPublicId): mixed
    {
        return DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');
    }
}
