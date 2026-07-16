<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Roles;

use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;

final readonly class InstallStarterRoles
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
        private StarterRoleCatalog $roles,
        private PermissionRoleStore $store,
    ) {}

    public function handle(): void
    {
        $this->store->ensurePermissions($this->permissions->all());

        foreach ($this->roles->definitions($this->permissions->names()) as $role) {
            if ($this->store->roleExists($role->name->value)) {
                continue;
            }

            $this->store->createRoleWithPermissions($role->name->value, $role->permissionNames);
        }
    }
}
