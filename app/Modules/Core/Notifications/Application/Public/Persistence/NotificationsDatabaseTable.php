<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class NotificationsDatabaseTable
{
    public const NOTIFICATIONS = DatabaseSchema::CORE_NOTIFICATIONS.'.notifications';

    public const NOTIFICATION_RECIPIENTS = DatabaseSchema::CORE_NOTIFICATIONS.'.notification_recipients';

    public const NOTIFICATION_EMAIL_ADDRESSES = DatabaseSchema::CORE_NOTIFICATIONS.'.notification_email_addresses';

    public const NOTIFICATION_EMAIL_PREFERENCES = DatabaseSchema::CORE_NOTIFICATIONS.'.notification_email_preferences';

    public const REALTIME_EVENTS = DatabaseSchema::CORE_NOTIFICATIONS.'.realtime_events';
}
