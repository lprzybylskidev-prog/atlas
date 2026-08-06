<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Inertia;

use App\Modules\Core\Users\Application\Permissions\UserPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class UsersRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'core.users.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            UserPermissionCatalog::ADMIN_USERS_INDEX,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [
            UserPermissionCatalog::USERS_PROFILE,
        ];
    }
}
