<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Permissions;

use App\Modules\Core\Notifications\Application\Public\Permissions\NotificationPermissionNames;
use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class NotificationPermissionCatalog implements ModulePermissionContribution
{
    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(NotificationPermissionNames::NOTIFICATIONS_INDEX, 'View own notification center.'),
            new ModulePermissionDefinition(NotificationPermissionNames::NOTIFICATIONS_READ, 'Mark own notifications as read.'),
            new ModulePermissionDefinition(NotificationPermissionNames::NOTIFICATIONS_READ_BULK, 'Mark own notifications as read in bulk.'),
            new ModulePermissionDefinition(NotificationPermissionNames::REALTIME_EVENTS, 'Receive authorized realtime events.'),
        ];
    }
}
