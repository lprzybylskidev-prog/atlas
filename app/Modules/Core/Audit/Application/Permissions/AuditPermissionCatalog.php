<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class AuditPermissionCatalog implements ModulePermissionContribution
{
    public const ADMIN_AUDIT_INDEX = 'admin.audit.index';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::ADMIN_AUDIT_INDEX, 'View audit and security audit records.'),
        ];
    }
}
