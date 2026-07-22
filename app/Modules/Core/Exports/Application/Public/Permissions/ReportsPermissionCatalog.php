<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class ReportsPermissionCatalog implements ModulePermissionContribution
{
    public const REQUEST = 'exports.request';

    public const DOWNLOAD = 'exports.download';

    public const PRINT = 'exports.print';

    public const AUDIT_EXPORT = 'exports.audit-export';

    public const ADMIN_INDEX = 'admin.exports.index';

    public const ADMIN_DATA_TABLE = 'admin.exports.data-table';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::REQUEST, 'Request authorized export artifacts.'),
            new ModulePermissionDefinition(self::DOWNLOAD, 'Download generated export artifacts after reauthorization.'),
            new ModulePermissionDefinition(self::PRINT, 'Render authorized export browser print layouts.'),
            new ModulePermissionDefinition(self::AUDIT_EXPORT, 'Export detailed audit/history datasets instead of ordinary final values.'),
            new ModulePermissionDefinition(self::ADMIN_INDEX, 'View export generation lifecycle status in Admin operations.'),
            new ModulePermissionDefinition(self::ADMIN_DATA_TABLE, 'Request Admin DataTable exports through the Core Exports lifecycle.'),
        ];
    }
}
