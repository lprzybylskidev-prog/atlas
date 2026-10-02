<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Infrastructure\Persistence;

use App\Modules\Core\Calendar\Application\Contracts\CalendarReminderStore;
use App\Modules\Core\Calendar\Infrastructure\Persistence\TableNames\CalendarDatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class DatabaseCalendarReminderStore implements CalendarReminderStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function claim(string $eventPublicId, string $occurrenceDate, int $minutesBefore): bool
    {
        return $this->database->table(CalendarDatabaseTable::REMINDER_DELIVERIES)->insertOrIgnore([
            'event_public_id' => $eventPublicId,
            'occurrence_date' => $occurrenceDate,
            'minutes_before' => $minutesBefore,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    public function delivered(string $eventPublicId, string $occurrenceDate, int $minutesBefore): void
    {
        $this->query($eventPublicId, $occurrenceDate, $minutesBefore)->update(['delivered_at' => now(), 'updated_at' => now()]);
    }

    private function query(string $eventPublicId, string $occurrenceDate, int $minutesBefore): Builder
    {
        return $this->database->table(CalendarDatabaseTable::REMINDER_DELIVERIES)
            ->where('event_public_id', $eventPublicId)
            ->where('occurrence_date', $occurrenceDate)
            ->where('minutes_before', $minutesBefore);
    }
}
