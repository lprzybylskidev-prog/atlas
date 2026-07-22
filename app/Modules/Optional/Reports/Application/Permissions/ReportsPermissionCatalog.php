<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class ReportsPermissionCatalog implements ModulePermissionContribution
{
    public const REQUEST = 'reports.request';

    public const DOWNLOAD = 'reports.download';

    public const AUDIT_EXPORT = 'reports.audit-export';

    public const ADMIN_INDEX = 'admin.reports.index';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::REQUEST, 'Request authorized report and export artifacts.'),
            new ModulePermissionDefinition(self::DOWNLOAD, 'Download generated report and export artifacts after reauthorization.'),
            new ModulePermissionDefinition(self::AUDIT_EXPORT, 'Export detailed audit/history datasets instead of ordinary final report values.'),
            new ModulePermissionDefinition(self::ADMIN_INDEX, 'View report and export generation lifecycle status in Admin operations.'),
        ];
    }
}
