<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\DTOs;

use App\Modules\Core\Calendar\Domain\Events\CalendarAvailability;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceRule;
use DateTimeImmutable;

final readonly class CalendarEventInput
{
    /** @param list<int> $reminderMinutes */
    public function __construct(
        public string $title,
        public ?string $description,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public bool $allDay,
        public ?string $location,
        public CalendarAvailability $availability,
        public ?RecurrenceRule $recurrence,
        public array $reminderMinutes,
    ) {}
}
