<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;

final class ImportsPermissionCatalog implements ModulePermissionContribution
{
    public function permissions(): array
    {
        return [];
    }
}
