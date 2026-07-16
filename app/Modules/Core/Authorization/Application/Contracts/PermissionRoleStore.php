<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Contracts;

use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

interface PermissionRoleStore
{
    /**
     * @param  list<ModulePermissionDefinition>  $permissions
     */
    public function ensurePermissions(array $permissions): void;

    public function roleExists(string $roleName): bool;

    /**
     * @param  list<string>  $permissionNames
     */
    public function createRoleWithPermissions(string $roleName, array $permissionNames): void;

    /**
     * @return list<string>
     */
    public function rolePermissionNames(string $roleName): array;

    /**
     * @param  list<string>  $permissionNames
     */
    public function grantPermissionsToRole(string $roleName, array $permissionNames): void;

    public function anyUserHasRole(string $roleName): bool;

    public function assignRoleToUserInTeam(string $userPublicId, string $teamPublicId, string $roleName): void;

    /**
     * @param  list<string>  $permissionNames
     */
    public function assignPermissionsToUserInTeam(string $userPublicId, string $teamPublicId, array $permissionNames): void;

    public function userHasOnboardingPackage(string $userPublicId): bool;

    public function recordUserOnboardingPackage(string $userPublicId, string $teamPublicId, string $packageName): void;

    public function copyAssignmentsBetweenUsers(string $sourceUserPublicId, string $targetUserPublicId, string $teamPublicId): void;
}
