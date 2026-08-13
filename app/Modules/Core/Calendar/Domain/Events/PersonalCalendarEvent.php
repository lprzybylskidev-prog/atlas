<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Domain\Events;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PersonalCalendarEvent
{
    /** @param list<int> $reminderMinutes */
    public function __construct(
        public string $publicId,
        public int $ownerUserId,
        public string $title,
        public ?string $description,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public bool $allDay,
        public ?string $location,
        public CalendarAvailability $availability,
        public ?RecurrenceRule $recurrence,
        public array $reminderMinutes,
        public int $version,
    ) {
        if (trim($publicId) === '' || trim($title) === '') {
            throw new InvalidArgumentException('Calendar event public ID and title are required.');
        }

        if ($ownerUserId < 1 || $endsAt <= $startsAt) {
            throw new InvalidArgumentException('Calendar event owner and time interval must be valid.');
        }

        if (count($reminderMinutes) !== count(array_unique($reminderMinutes))) {
            throw new InvalidArgumentException('Calendar event reminders must be unique.');
        }

        foreach ($reminderMinutes as $minutes) {
            if ($minutes < 0 || $minutes > 525600) {
                throw new InvalidArgumentException('Calendar event reminder must be between 0 and 525600 minutes.');
            }
        }
    }
}
