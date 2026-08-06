<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Presentation\Inertia;

use App\Modules\Optional\Integrations\Application\Permissions\IntegrationsPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class IntegrationsRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'optional.integrations.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            IntegrationsPermissionCatalog::ADMIN_INTEGRATIONS_INDEX,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [];
    }
}
