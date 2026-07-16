<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Packages;

use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use InvalidArgumentException;

final readonly class PackageRoleManager
{
    public function __construct(
        private OnboardingPackageCatalog $packages,
        private PermissionCatalogRegistry $permissions,
        private PermissionRoleStore $store,
    ) {}

    public function diff(string $packageName, string $roleName): RolePackageDiff
    {
        $package = $this->package($packageName);
        $current = $this->store->rolePermissionNames($roleName);

        return new RolePackageDiff(
            missingPermissionNames: array_values(array_diff($package->templatePermissionNames, $current)),
            unchangedExtraPermissionNames: array_values(array_diff($current, $package->templatePermissionNames)),
        );
    }

    public function createRoleFromPackage(string $packageName, string $roleName): void
    {
        $package = $this->package($packageName);

        $this->store->ensurePermissions($this->permissions->all());
        $this->store->createRoleWithPermissions($roleName, $package->templatePermissionNames);
    }

    public function addMissingPermissionsToRole(string $packageName, string $roleName): RolePackageDiff
    {
        $diff = $this->diff($packageName, $roleName);

        if ($diff->missingPermissionNames !== []) {
            $this->store->grantPermissionsToRole($roleName, $diff->missingPermissionNames);
        }

        return $diff;
    }

    private function package(string $packageName): OnboardingPackageDefinition
    {
        $package = $this->packages->get($packageName);

        if (! $package instanceof OnboardingPackageDefinition) {
            throw new InvalidArgumentException(sprintf('Onboarding package [%s] is not registered.', $packageName));
        }

        return $package;
    }
}
