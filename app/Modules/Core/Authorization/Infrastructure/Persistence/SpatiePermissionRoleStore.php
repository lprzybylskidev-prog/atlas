<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Audit\Enums\SecurityAuditCategory;
use App\Shared\Application\Authorization\Contracts\AdministratorAccessLookup;
use App\Shared\Application\Authorization\Contracts\UserTeamAuthorizationCleaner;
use App\Shared\Application\Authorization\Contracts\UserTeamAuthorizationManager;
use App\Shared\Application\Authorization\DTOs\UserTeamAuthorizationAssignments;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class SpatiePermissionRoleStore implements AdministratorAccessLookup, PermissionRoleStore, UserTeamAuthorizationCleaner, UserTeamAuthorizationManager
{
    private const ADMINISTRATOR_ROLE_NAME = 'system.administrator';

    private const ADMIN_MODE_ENTER_PERMISSION = 'admin-mode.enter';

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PermissionCatalogRegistry $permissionCatalog,
        private readonly UserLookup $users,
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

    public function hasAdministratorLevelAccess(string $userPublicId): bool
    {
        $userId = $this->userId($userPublicId);

        if (! is_int($userId)) {
            return false;
        }

        $modelType = config('auth.providers.users.model');
        $modelType = is_string($modelType) && $modelType !== '' ? $modelType : 'App\\Modules\\Core\\Identity\\Infrastructure\\Persistence\\User';

        if (DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)
            ->join(AuthorizationDatabaseTable::ROLES, 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', $modelType)
            ->where('roles.name', self::ADMINISTRATOR_ROLE_NAME)
            ->exists()) {
            return true;
        }

        return DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)
            ->join(AuthorizationDatabaseTable::PERMISSIONS, 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.model_type', $modelType)
            ->where('permissions.name', self::ADMIN_MODE_ENTER_PERMISSION)
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

        $this->membershipProvisioner()->ensureUserTeamMembership($userPublicId, $teamPublicId);

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

        $this->membershipProvisioner()->ensureUserTeamMembership($targetUserPublicId, $teamPublicId);

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

        DB::table(AuthorizationDatabaseTable::USER_TEAM_ASSIGNMENT_PROVENANCE)
            ->where('user_id', $userId)
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

        $provenance = DB::table(AuthorizationDatabaseTable::USER_TEAM_ASSIGNMENT_PROVENANCE)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->first();
        $values = $provenance === null ? [] : get_object_vars($provenance);
        $copiedFromUserId = $this->nullableInt($values['copied_from_user_id'] ?? null);

        return new UserTeamAuthorizationAssignments(
            userPublicId: $userPublicId,
            teamPublicId: $teamPublicId,
            roleNames: $roles,
            directPermissionNames: $permissions,
            provenancePublicId: $this->nullableString($values['public_id'] ?? null),
            sourceType: $this->stringValue($values['source_type'] ?? 'manual', 'manual'),
            sourcePublicId: $this->nullableString($values['source_public_id'] ?? null),
            sourceDisplayNameSnapshot: $this->nullableString($values['source_display_name_snapshot'] ?? null),
            copiedFromUserPublicId: $copiedFromUserId === null ? null : $this->users->publicIdForInternalId($copiedFromUserId),
            presetVersion: $this->nullableInt($values['preset_version'] ?? null),
            presetSnapshot: $this->jsonObject($values['preset_snapshot'] ?? null),
            appliedAt: $this->nullableString($values['applied_at'] ?? null),
            reason: $this->nullableString($values['reason'] ?? null),
            resultingLimits: $this->jsonLimits($values['resulting_limits'] ?? null),
            divergedAt: $this->nullableString($values['diverged_at'] ?? null),
            version: $this->nullableInt($values['version'] ?? null) ?? 0,
        );
    }

    public function replaceAssignmentsForUserTeam(
        string $actorPublicId,
        string $userPublicId,
        string $teamPublicId,
        array $roleNames,
        array $directPermissionNames,
        ?string $reason = null,
        string $sourceType = 'manual',
        ?string $sourcePublicId = null,
        ?string $sourceDisplayNameSnapshot = null,
        ?string $copiedFromUserPublicId = null,
        ?int $presetVersion = null,
        ?array $presetSnapshot = null,
        array $resultingLimits = [],
        ?int $expectedVersion = null,
    ): void {
        $userId = $this->userId($userPublicId);
        $teamId = $this->teamId($teamPublicId);

        if (! is_int($userId) || ! is_int($teamId)) {
            return;
        }

        $before = $this->assignmentsForUserTeam($userPublicId, $teamPublicId);
        $roleNames = $this->validRoleNames($roleNames);
        $directPermissionNames = $this->validPermissionNames($directPermissionNames);
        $actorUserId = $this->userId($actorPublicId);
        $copiedFromUserId = $copiedFromUserPublicId === null ? null : $this->userId($copiedFromUserPublicId);
        $reason = trim((string) $reason);

        if (! in_array($sourceType, ['manual', 'preset', 'copy'], true)) {
            throw new \InvalidArgumentException('Unknown authorization assignment source type.');
        }

        if ($expectedVersion !== null && $before->version !== $expectedVersion) {
            throw ValidationException::withMessages([
                'expected_version' => __('validation.custom.authorization_assignment.stale'),
            ]);
        }

        $isInitialApplication = $before->provenancePublicId === null;
        $effectiveSourceType = $isInitialApplication ? $sourceType : $before->sourceType;
        $effectiveSourcePublicId = $isInitialApplication ? $sourcePublicId : $before->sourcePublicId;
        $effectiveSourceDisplay = $isInitialApplication ? $sourceDisplayNameSnapshot : $before->sourceDisplayNameSnapshot;
        $effectiveCopiedFromUserId = $isInitialApplication ? $copiedFromUserId : ($before->copiedFromUserPublicId === null ? null : $this->userId($before->copiedFromUserPublicId));
        $effectivePresetVersion = $isInitialApplication ? $presetVersion : $before->presetVersion;
        $effectivePresetSnapshot = $isInitialApplication ? $presetSnapshot : $before->presetSnapshot;
        $diverged = ! $isInitialApplication && (
            $before->roleNames !== $roleNames
            || $before->directPermissionNames !== $directPermissionNames
            || $before->resultingLimits !== $resultingLimits
        );

        DB::transaction(function () use ($userId, $teamId, $roleNames, $directPermissionNames, $actorUserId, $reason, $isInitialApplication, $effectiveSourceType, $effectiveSourcePublicId, $effectiveSourceDisplay, $effectiveCopiedFromUserId, $effectivePresetVersion, $effectivePresetSnapshot, $resultingLimits, $diverged, $before, $actorPublicId, $userPublicId, $teamPublicId): void {
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

            $now = now();
            $provenanceValues = [
                'source_type' => $effectiveSourceType,
                'source_public_id' => $effectiveSourcePublicId,
                'source_display_name_snapshot' => $effectiveSourceDisplay,
                'copied_from_user_id' => $effectiveCopiedFromUserId,
                'preset_version' => $effectivePresetVersion,
                'preset_snapshot' => $effectivePresetSnapshot === null ? null : json_encode($effectivePresetSnapshot, JSON_THROW_ON_ERROR),
                'reason' => $isInitialApplication ? ($reason !== '' ? $reason : 'Authorization assignment applied.') : ($before->reason ?? 'Authorization assignment applied.'),
                'resulting_role_names' => json_encode($roleNames, JSON_THROW_ON_ERROR),
                'resulting_direct_permission_names' => json_encode($directPermissionNames, JSON_THROW_ON_ERROR),
                'resulting_limits' => json_encode($resultingLimits, JSON_THROW_ON_ERROR),
                'diverged_at' => $diverged ? ($before->divergedAt ?? $now) : $before->divergedAt,
                'updated_by_user_id' => $actorUserId,
                'update_reason' => $isInitialApplication ? null : ($reason !== '' ? $reason : null),
                'version' => max(1, $before->version + 1),
                'updated_at' => $now,
            ];

            if ($isInitialApplication) {
                $provenanceValues += [
                    'public_id' => (string) Str::ulid(),
                    'user_id' => $userId,
                    'team_id' => $teamId,
                    'applied_by_user_id' => $actorUserId,
                    'applied_at' => $now,
                    'created_at' => $now,
                ];
            }

            DB::table(AuthorizationDatabaseTable::USER_TEAM_ASSIGNMENT_PROVENANCE)->updateOrInsert(
                ['user_id' => $userId, 'team_id' => $teamId],
                $provenanceValues,
            );

            if ($isInitialApplication && $effectiveSourceType === 'preset' && is_array($effectivePresetSnapshot)) {
                $packageName = $effectivePresetSnapshot['name'] ?? null;

                if (is_string($packageName) && $packageName !== '') {
                    DB::table(AuthorizationDatabaseTable::USER_ONBOARDING_PACKAGES)->updateOrInsert(
                        ['user_id' => $userId, 'team_id' => $teamId],
                        ['package_name' => $packageName, 'created_at' => $now, 'updated_at' => $now],
                    );
                }
            }

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
                    'version' => $before->version,
                ],
                after: [
                    'roles' => $roleNames,
                    'direct_permissions' => $directPermissionNames,
                    'source_type' => $effectiveSourceType,
                    'diverged' => $diverged,
                    'version' => max(1, $before->version + 1),
                    'reason' => $reason,
                ],
                security: true,
                securityCategory: SecurityAuditCategory::Authorization,
            ));
        });
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /** @return array<string, mixed>|null */
    private function jsonObject(mixed $value): ?array
    {
        if (is_array($value)) {
            return $this->stringKeyedArray($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $this->stringKeyedArray($decoded) : null;
    }

    /** @return array<string, int|null> */
    private function jsonLimits(mixed $value): array
    {
        $decoded = $this->jsonObject($value);

        if ($decoded === null) {
            return [];
        }

        $limits = [];

        foreach ($decoded as $key => $limit) {
            if (is_numeric($limit) || $limit === null) {
                $limits[$key] = $limit === null ? null : (int) $limit;
            }
        }

        return $limits;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
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
        return $this->users->internalIdForPublicId($userPublicId);
    }

    private function teamId(string $teamPublicId): mixed
    {
        return app(TeamLookup::class)->internalIdForPublicId($teamPublicId);
    }

    private function membershipProvisioner(): UserTeamMembershipProvisioner
    {
        return app(UserTeamMembershipProvisioner::class);
    }
}
