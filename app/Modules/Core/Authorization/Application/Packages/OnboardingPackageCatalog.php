<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Packages;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;

final readonly class OnboardingPackageCatalog
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
        private OnboardingPackageStore $store,
    ) {}

    /**
     * @return list<OnboardingPackageDefinition>
     */
    public function all(): array
    {
        return array_merge($this->store->allActive(), [
            new OnboardingPackageDefinition(
                publicId: 'system.core.administrator',
                name: 'core.administrator',
                label: 'Core administrator baseline',
                initialRoleNames: [StarterRoleName::Administrator->value],
                directPermissionNames: [],
                templatePermissionNames: $this->permissions->names(),
            ),
        ]);
    }

    public function get(string $name): ?OnboardingPackageDefinition
    {
        foreach ($this->all() as $package) {
            if ($package->name === $name) {
                return $package;
            }
        }

        return null;
    }
}
