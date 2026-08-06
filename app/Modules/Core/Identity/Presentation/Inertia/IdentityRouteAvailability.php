<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Inertia;

use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class IdentityRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'core.identity.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            'admin.rate-limits.index',
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [];
    }
}
