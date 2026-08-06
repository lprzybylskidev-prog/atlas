<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Presentation\Inertia;

use App\Modules\Optional\ManagedProcesses\Application\Permissions\ManagedProcessesPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class ManagedProcessesRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'optional.managed-processes.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            ManagedProcessesPermissionCatalog::INDEX,
            ManagedProcessesPermissionCatalog::DEFINITIONS_INDEX,
            ManagedProcessesPermissionCatalog::SCHEDULES_INDEX,
            ManagedProcessesPermissionCatalog::SCHEDULES_CREATE,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [];
    }
}
