<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Presentation\Inertia;

use App\Modules\Core\Calendar\Application\Permissions\CalendarPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class CalendarRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'core.calendar.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [];
    }

    public function applicationRoutes(Request $request): array
    {
        return [
            CalendarPermissionCatalog::INDEX,
            CalendarPermissionCatalog::EVENT_STORE,
            CalendarPermissionCatalog::EVENT_UPDATE,
            CalendarPermissionCatalog::EVENT_DESTROY,
            CalendarPermissionCatalog::PREFERENCE_UPDATE,
        ];
    }
}
