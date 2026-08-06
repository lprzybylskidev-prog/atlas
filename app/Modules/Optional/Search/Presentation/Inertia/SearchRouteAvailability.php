<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Presentation\Inertia;

use App\Modules\Optional\Search\Application\Permissions\SearchPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class SearchRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'optional.search.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            SearchPermissionCatalog::ADMIN_INDEX,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [];
    }
}
