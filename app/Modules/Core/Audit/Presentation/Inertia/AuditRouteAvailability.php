<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Presentation\Inertia;

use App\Modules\Core\Audit\Application\Permissions\AuditPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class AuditRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'core.audit.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            AuditPermissionCatalog::ADMIN_AUDIT_INDEX,
            AuditPermissionCatalog::ADMIN_AUDIT_SECURITY_HISTORY,
            AuditPermissionCatalog::ADMIN_AUDIT_IMPERSONATION_SHOW,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [];
    }
}
