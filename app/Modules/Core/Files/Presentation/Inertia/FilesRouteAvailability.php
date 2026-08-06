<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Presentation\Inertia;

use App\Modules\Core\Files\Application\Permissions\FilesPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class FilesRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'core.files.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            FilesPermissionCatalog::ADMIN_FILES_INDEX,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        return [];
    }
}
