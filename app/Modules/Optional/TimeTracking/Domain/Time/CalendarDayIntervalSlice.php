<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Domain\Time;

use DateTimeImmutable;

final readonly class CalendarDayIntervalSlice
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $calendarDate,
        public int $seconds,
    ) {}
}
