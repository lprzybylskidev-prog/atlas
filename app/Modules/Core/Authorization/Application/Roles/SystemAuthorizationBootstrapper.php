<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Roles;

use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Public\Contracts\AuthorizationBootstrapper;

final readonly class SystemAuthorizationBootstrapper implements AuthorizationBootstrapper
{
    public function __construct(
        private InstallStarterRoles $roles,
        private PermissionRoleStore $store,
        private PermissionCatalogRegistry $permissions,
    ) {}

    public function synchronizeTechnicalFoundation(): void
    {
        $this->roles->handle();
        $missing = array_values(array_diff(
            $this->permissions->names(),
            $this->store->rolePermissionNames(StarterRoleName::Administrator->value),
        ));

        if ($missing !== []) {
            $this->store->grantPermissionsToRole(StarterRoleName::Administrator->value, $missing);
        }
    }
}
