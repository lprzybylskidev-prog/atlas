<?php

declare(strict_types=1);

namespace App\Shared\Application\Calendar\Permissions;

final class CalendarPermissionNames
{
    public const INDEX = 'calendar.index';

    public const EVENT_STORE = 'calendar.events.store';

    public const EVENT_UPDATE = 'calendar.events.update';

    public const EVENT_DESTROY = 'calendar.events.destroy';

    public const PREFERENCE_UPDATE = 'calendar.preferences.update';
}
