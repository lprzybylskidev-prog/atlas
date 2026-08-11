<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\Permissions;

use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class ReportsPermissionCatalog implements ModulePermissionContribution
{
    public const REQUEST = ExportPermissions::REQUEST;

    public const DOWNLOAD = ExportPermissions::DOWNLOAD;

    public const PRINT_EXPORT = ExportPermissions::PRINT;

    public const AUDIT_EXPORT = ExportPermissions::AUDIT_EXPORT;

    public const DATA_TABLE = ExportPermissions::DATA_TABLE;

    public const ADMIN_INDEX = ExportPermissions::ADMIN_INDEX;

    public const ADMIN_DATA_TABLE = ExportPermissions::ADMIN_DATA_TABLE;

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::REQUEST, 'Request authorized export artifacts.'),
            new ModulePermissionDefinition(self::DOWNLOAD, 'Download generated export artifacts after reauthorization.'),
            new ModulePermissionDefinition(self::PRINT_EXPORT, 'Render authorized browser print layouts.'),
            new ModulePermissionDefinition(self::AUDIT_EXPORT, 'Export detailed audit/history datasets instead of ordinary final values.'),
            new ModulePermissionDefinition(self::DATA_TABLE, 'Request application DataTable exports through the Core Exports lifecycle.'),
            new ModulePermissionDefinition(self::ADMIN_INDEX, 'View export generation lifecycle status in Admin operations.'),
            new ModulePermissionDefinition(self::ADMIN_DATA_TABLE, 'Request Admin DataTable exports through the Core Exports lifecycle.'),
        ];
    }
}
