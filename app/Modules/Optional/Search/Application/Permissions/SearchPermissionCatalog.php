<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class SearchPermissionCatalog implements ModulePermissionContribution
{
    public const QUERY = 'search.query';

    public const ADMIN_INDEX = 'admin.search.index';

    public const ADMIN_REBUILD = 'admin.search.rebuild';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::QUERY, 'Use enabled full-text search projections.'),
            new ModulePermissionDefinition(self::ADMIN_INDEX, 'View search health, index lag, sync, and discrepancy status.'),
            new ModulePermissionDefinition(self::ADMIN_REBUILD, 'Start confirmed audited search index rebuilds.'),
        ];
    }
}
