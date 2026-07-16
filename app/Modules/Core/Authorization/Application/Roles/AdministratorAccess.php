<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Roles;

use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;

final readonly class AdministratorAccess implements AdministratorAccessManager
{
    public function __construct(
        private InstallStarterRoles $starterRoles,
        private PermissionRoleStore $store,
    ) {}

    public function administratorExists(): bool
    {
        return $this->store->anyUserHasRole(StarterRoleName::Administrator->value);
    }

    public function assignAdministrator(string $userPublicId, string $teamPublicId): void
    {
        $this->starterRoles->handle();

        $this->store->assignRoleToUserInTeam(
            userPublicId: $userPublicId,
            teamPublicId: $teamPublicId,
            roleName: StarterRoleName::Administrator->value,
        );
    }
}
