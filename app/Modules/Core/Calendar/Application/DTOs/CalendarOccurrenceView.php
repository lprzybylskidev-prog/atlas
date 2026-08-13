<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\DTOs;

final readonly class CalendarOccurrenceView
{
    /**
     * @param  list<int>  $recurrenceWeekdays
     * @param  list<int>  $reminderMinutes
     */
    public function __construct(
        public string $eventPublicId,
        public string $occurrenceDate,
        public string $title,
        public ?string $description,
        public string $startsAt,
        public string $endsAt,
        public bool $allDay,
        public ?string $location,
        public string $availability,
        public bool $recurring,
        public ?string $recurrenceFrequency,
        public int $recurrenceInterval,
        public array $recurrenceWeekdays,
        public ?string $recurrenceEndsOn,
        public ?int $recurrenceCount,
        public array $reminderMinutes,
        public int $version,
    ) {}
}
