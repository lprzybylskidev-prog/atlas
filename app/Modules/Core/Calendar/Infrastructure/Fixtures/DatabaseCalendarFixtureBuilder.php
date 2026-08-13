<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Infrastructure\Fixtures;

use App\Modules\Core\Calendar\Application\Contracts\CalendarFixtureBuilder;
use App\Modules\Core\Calendar\Infrastructure\Persistence\TableNames\CalendarDatabaseTable;
use Illuminate\Database\ConnectionInterface;
use UnexpectedValueException;

final readonly class DatabaseCalendarFixtureBuilder implements CalendarFixtureBuilder
{
    public function __construct(private ConnectionInterface $database) {}

    public function provideVisibilityEvents(int $userId): void
    {
        $this->database->transaction(function () use ($userId): void {
            $this->upsert($userId, '01K2K7H1C00000000000000001', 'Weekly portfolio review', '2026-08-13 07:00:00+00', '2026-08-13 08:00:00+00', 'weekly', [4]);
            $this->upsert($userId, '01K2K7H1C00000000000000002', 'Court deadline', '2026-08-18 22:00:00+00', '2026-08-19 22:00:00+00', null, []);
        });
    }

    /** @param list<int> $weekdays */
    private function upsert(int $userId, string $publicId, string $title, string $startsAt, string $endsAt, ?string $frequency, array $weekdays): void
    {
        $this->database->table(CalendarDatabaseTable::PERSONAL_EVENTS)->updateOrInsert(
            ['public_id' => $publicId],
            [
                'user_id' => $userId,
                'title' => $title,
                'description' => 'Deterministic Calendar browser-review event.',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'all_day' => $frequency === null,
                'location' => $frequency === null ? null : 'Review room',
                'availability' => 'busy',
                'recurrence_frequency' => $frequency,
                'recurrence_interval' => 1,
                'recurrence_weekdays' => $frequency === null ? null : json_encode($weekdays, JSON_THROW_ON_ERROR),
                'recurrence_count' => $frequency === null ? null : 6,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $eventIdValue = $this->database->table(CalendarDatabaseTable::PERSONAL_EVENTS)->where('public_id', $publicId)->value('id');
        if (! is_int($eventIdValue) && ! (is_string($eventIdValue) && ctype_digit($eventIdValue))) {
            throw new UnexpectedValueException('Calendar fixture event ID is unavailable.');
        }
        $eventId = (int) $eventIdValue;
        $this->database->table(CalendarDatabaseTable::REMINDERS)->updateOrInsert(
            ['event_id' => $eventId, 'minutes_before' => 15],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }
}
