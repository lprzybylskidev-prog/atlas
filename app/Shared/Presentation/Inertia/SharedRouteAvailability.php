<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Inertia;

use App\Modules\Core\Authorization\Application\Public\Permissions\CoreAuthorizationPermissionNames;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class SharedRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'shared.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            'admin.system-status',
            'admin.system-status.release',
            'admin.system-status.readiness',
            'admin.system-status.modules',
            'admin.system-status.scheduler',
            'admin.system-status.module-activation',
            'admin.system-status.failed-jobs',
            'admin.modules.index',
            'admin.queues.index',
            'admin.logs.index',
            'admin.pulse.view',
            'admin.telescope.view',
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [
            CoreAuthorizationPermissionNames::DASHBOARD,
        ];
    }
}
