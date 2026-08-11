<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Inertia;

use App\Modules\Core\Teams\Application\Permissions\TeamPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class TeamsRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'core.teams.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            TeamPermissionCatalog::ADMIN_TEAMS_INDEX,
            TeamPermissionCatalog::ADMIN_TEAMS_STRUCTURE_SHOW,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [];
    }
}
