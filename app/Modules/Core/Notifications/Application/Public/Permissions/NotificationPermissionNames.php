<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\Permissions;

final class NotificationPermissionNames
{
    public const NOTIFICATIONS_INDEX = 'users.notifications.index';

    public const NOTIFICATIONS_READ = 'users.notifications.read';

    public const NOTIFICATIONS_READ_BULK = 'users.notifications.read.bulk';

    public const REALTIME_EVENTS = 'notifications.realtime.events';
}
