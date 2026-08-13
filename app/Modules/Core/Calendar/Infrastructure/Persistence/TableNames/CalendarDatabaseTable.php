<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Infrastructure\Persistence\TableNames;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class CalendarDatabaseTable
{
    public const PERSONAL_EVENTS = DatabaseSchema::CORE_CALENDAR.'.personal_events';

    public const CONTRIBUTED_EVENTS = DatabaseSchema::CORE_CALENDAR.'.contributed_events';

    public const REMINDERS = DatabaseSchema::CORE_CALENDAR.'.reminders';

    public const RECURRENCE_EXCEPTIONS = DatabaseSchema::CORE_CALENDAR.'.recurrence_exceptions';

    public const USER_PREFERENCES = DatabaseSchema::CORE_CALENDAR.'.user_preferences';
}
