<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Infrastructure\Persistence;

use App\Modules\Core\Calendar\Application\Contracts\CalendarPreferenceStore;
use App\Modules\Core\Calendar\Application\DTOs\CalendarPreference;
use App\Modules\Core\Calendar\Infrastructure\Persistence\TableNames\CalendarDatabaseTable;
use Illuminate\Database\ConnectionInterface;
use UnexpectedValueException;

final readonly class DatabaseCalendarPreferenceStore implements CalendarPreferenceStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function forUser(int $userId): CalendarPreference
    {
        $row = $this->database->table(CalendarDatabaseTable::USER_PREFERENCES)->where('user_id', $userId)->first();

        if (! is_object($row)) {
            return new CalendarPreference;
        }

        return new CalendarPreference(
            defaultReminderMinutes: $this->requiredInt($row->default_reminder_minutes),
            emailEnabled: (bool) $row->email_enabled,
        );
    }

    public function save(int $userId, CalendarPreference $preference): void
    {
        $now = now();

        $this->database->table(CalendarDatabaseTable::USER_PREFERENCES)->updateOrInsert(
            ['user_id' => $userId],
            [
                'default_reminder_minutes' => $preference->defaultReminderMinutes,
                'email_enabled' => $preference->emailEnabled,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function requiredInt(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new UnexpectedValueException('Calendar preferences persistence returned a non-integer value.');
        }

        return (int) $value;
    }
}
