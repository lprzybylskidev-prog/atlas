<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class ImportsPermissionCatalog implements ModulePermissionContribution
{
    public const INDEX = 'admin.imports.index';

    public const SHOW = 'admin.imports.show';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::INDEX, 'View import executions, sources, statistics, and idempotency state.'),
            new ModulePermissionDefinition(self::SHOW, 'View import source metadata, mapping, preview, and row or field errors.'),
        ];
    }
}
