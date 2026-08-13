<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Domain\Events;

use DateTimeImmutable;

final readonly class CalendarOccurrence
{
    public function __construct(
        public string $eventPublicId,
        public string $occurrenceDate,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {}
}
