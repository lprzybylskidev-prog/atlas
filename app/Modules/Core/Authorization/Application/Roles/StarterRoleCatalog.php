<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Roles;

use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Teams\Application\Public\Permissions\TeamPermissionNames;

final class StarterRoleCatalog
{
    /**
     * @param  list<string>  $allPermissionNames
     * @return list<StarterRoleDefinition>
     */
    public function definitions(array $allPermissionNames): array
    {
        return [
            new StarterRoleDefinition(StarterRoleName::User, [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
            ]),
            new StarterRoleDefinition(StarterRoleName::Manager, [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                TeamPermissionNames::MANAGERS_VIEW,
            ]),
            new StarterRoleDefinition(StarterRoleName::Administrator, $allPermissionNames),
        ];
    }
}
