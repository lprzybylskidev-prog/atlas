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
    public function all(?string $teamPublicId = null): array
    {
        return $this->store->allActive($teamPublicId);
    }

    public function get(string $name, ?string $teamPublicId = null): ?OnboardingPackageDefinition
    {
        if ($teamPublicId === null) {
            foreach ($this->store->allActive() as $package) {
                if ($package->name === $name) {
                    return $package;
                }
            }

            return null;
        }

        if ($name === 'core.administrator') {
            return $this->systemAdministratorPreset($teamPublicId);
        }

        return $this->store->findActiveForTeam($name, $teamPublicId);
    }

    public function getByPublicId(string $publicId): ?OnboardingPackageDefinition
    {
        return $this->store->findByPublicId($publicId);
    }

    private function systemAdministratorPreset(string $teamPublicId): OnboardingPackageDefinition
    {
        return new OnboardingPackageDefinition(
            publicId: 'system.core.administrator.'.$teamPublicId,
            teamPublicId: $teamPublicId,
            teamName: '',
            name: 'core.administrator',
            label: 'Core administrator baseline',
            initialRoleNames: [StarterRoleName::Administrator->value],
            directPermissionNames: [],
            templatePermissionNames: $this->permissions->names(),
        );
    }
}
