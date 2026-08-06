<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Presentation\Inertia;

use App\Modules\Core\Privacy\Application\Permissions\PrivacyPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class PrivacyRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'core.privacy.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            PrivacyPermissionCatalog::ADMIN_INDEX,
            PrivacyPermissionCatalog::LEGAL_HOLDS_INDEX,
            PrivacyPermissionCatalog::LEGAL_HOLDS_CREATE,
            PrivacyPermissionCatalog::OPERATIONS_INDEX,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [];
    }
}
