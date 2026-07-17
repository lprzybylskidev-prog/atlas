<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class AuditPermissionCatalog implements ModulePermissionContribution
{
    public const ADMIN_AUDIT_INDEX = 'admin.audit.index';

    public const ADMIN_AUDIT_IMPERSONATION_SHOW = 'admin.audit.impersonation.show';

    public const ADMIN_AUDIT_SECURITY_HISTORY = 'admin.audit.security-history.index';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::ADMIN_AUDIT_INDEX, 'View audit and security audit records.'),
            new ModulePermissionDefinition(self::ADMIN_AUDIT_IMPERSONATION_SHOW, 'View an impersonation audit session detail.'),
            new ModulePermissionDefinition(self::ADMIN_AUDIT_SECURITY_HISTORY, 'View security history for all users.'),
        ];
    }
}
