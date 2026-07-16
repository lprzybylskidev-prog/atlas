<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

final class AdminTableDefinitions
{
    public const USERS = 'admin.users';

    public const TEAMS = 'admin.teams';

    public const ROLES = 'admin.authorization.roles';

    public const PACKAGES = 'admin.authorization.packages';

    public const PERMISSIONS = 'admin.authorization.permissions';

    public static function get(string $key): TableDefinition
    {
        return match ($key) {
            self::USERS => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('name'),
                new TableColumn('email'),
                new TableColumn('isActive', searchable: false),
                new TableColumn('emailVerified', searchable: false),
                new TableColumn('firstPasswordSet', searchable: false),
                new TableColumn('loginLocked', searchable: false),
                new TableColumn('mfaEnabled', searchable: false),
                new TableColumn('emailVerifiedAt', defaultVisible: false),
                new TableColumn('twoFactorConfirmedAt', defaultVisible: false),
                new TableColumn('firstPasswordSetAt', defaultVisible: false),
                new TableColumn('deactivatedAt', defaultVisible: false),
                new TableColumn('failedLoginAttempts', searchable: false, defaultVisible: false),
                new TableColumn('loginLockCount', searchable: false, defaultVisible: false),
                new TableColumn('loginLockedUntil', defaultVisible: false),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('updatedAt', defaultVisible: false),
            ], 'name'),
            self::TEAMS => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('name'),
                new TableColumn('isActive', searchable: false),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('updatedAt', defaultVisible: false),
            ], 'name'),
            self::ROLES => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('teamId', searchable: false, defaultVisible: false),
                new TableColumn('name'),
                new TableColumn('guard'),
                new TableColumn('permissionsCount', searchable: false),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('updatedAt', defaultVisible: false),
            ], 'name'),
            self::PACKAGES => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('label'),
                new TableColumn('name'),
                new TableColumn('initialRoles'),
                new TableColumn('directPermissions', defaultVisible: false),
                new TableColumn('templatePermissions', defaultVisible: false),
                new TableColumn('isActive', searchable: false, defaultVisible: false),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('updatedAt', defaultVisible: false),
            ], 'label'),
            self::PERMISSIONS => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('name'),
                new TableColumn('guard', defaultVisible: false),
                new TableColumn('description'),
                new TableColumn('module'),
                new TableColumn('teamScoped', searchable: false),
                new TableColumn('moduleActivation'),
                new TableColumn('assigned', searchable: false),
                new TableColumn('effective', searchable: false),
                new TableColumn('ineffectiveReason', defaultVisible: false),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('updatedAt', defaultVisible: false),
            ], 'name'),
            default => abort(404),
        };
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [self::USERS, self::TEAMS, self::ROLES, self::PACKAGES, self::PERMISSIONS];
    }
}
