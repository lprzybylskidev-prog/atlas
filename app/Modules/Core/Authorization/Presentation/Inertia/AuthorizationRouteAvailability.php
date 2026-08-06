<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Inertia;

use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class AuthorizationRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'core.authorization.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_ROLES,
            CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PACKAGES,
            CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [];
    }
}
