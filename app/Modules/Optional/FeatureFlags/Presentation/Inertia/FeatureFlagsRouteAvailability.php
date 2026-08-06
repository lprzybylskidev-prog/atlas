<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Presentation\Inertia;

use App\Modules\Optional\FeatureFlags\Application\Permissions\FeatureFlagsPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class FeatureFlagsRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'optional.feature-flags.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            FeatureFlagsPermissionCatalog::ADMIN_INDEX,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [];
    }
}
