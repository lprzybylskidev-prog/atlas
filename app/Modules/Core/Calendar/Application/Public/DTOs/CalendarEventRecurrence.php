<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Public\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CalendarEventRecurrence
{
    /** @param list<int> $weekdays */
    public function __construct(
        public string $frequency,
        public array $weekdays = [],
        public ?DateTimeImmutable $endsOn = null,
        public ?int $occurrenceCount = null,
    ) {
        if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            throw new InvalidArgumentException('Calendar contribution recurrence frequency is invalid.');
        }
    }
}
