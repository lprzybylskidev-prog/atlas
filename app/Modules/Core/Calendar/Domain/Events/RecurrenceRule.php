<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Domain\Events;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RecurrenceRule
{
    /** @param list<int> $weekdays */
    public function __construct(
        public RecurrenceFrequency $frequency,
        public int $interval = 1,
        public array $weekdays = [],
        public ?DateTimeImmutable $endsOn = null,
        public ?int $occurrenceCount = null,
    ) {
        if ($interval < 1 || $interval > 365) {
            throw new InvalidArgumentException('Recurrence interval must be between 1 and 365.');
        }

        if ($occurrenceCount !== null && ($occurrenceCount < 1 || $occurrenceCount > 1000)) {
            throw new InvalidArgumentException('Recurrence occurrence count must be between 1 and 1000.');
        }

        if (count($weekdays) !== count(array_unique($weekdays))) {
            throw new InvalidArgumentException('Recurrence weekdays must be unique.');
        }

        foreach ($weekdays as $weekday) {
            if ($weekday < 1 || $weekday > 7) {
                throw new InvalidArgumentException('Recurrence weekdays must use ISO values 1 through 7.');
            }
        }
    }
}
