<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Inertia;

use App\Modules\Core\Notifications\Application\Public\Permissions\NotificationPermissionNames;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final class NotificationsRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function key(): string
    {
        return 'core.notifications.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [];
    }

    public function applicationRoutes(Request $request): array
    {
        return [
            NotificationPermissionNames::NOTIFICATIONS_INDEX,
        ];
    }
}
