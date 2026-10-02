<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Meetings;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MeetingRecurrence
{
    /** @param list<int> $weekdays */
    public function __construct(
        public string $frequency,
        public array $weekdays = [],
        public ?DateTimeImmutable $endsOn = null,
        public ?int $occurrenceCount = null,
    ) {
        if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            throw new InvalidArgumentException('Meeting recurrence frequency is invalid.');
        }
        if ($weekdays !== [] && ($frequency !== 'weekly' || array_diff($weekdays, [1, 2, 3, 4, 5, 6, 7]) !== [])) {
            throw new InvalidArgumentException('Meeting recurrence weekdays are invalid.');
        }
        if ($occurrenceCount !== null && ($occurrenceCount < 1 || $occurrenceCount > 500)) {
            throw new InvalidArgumentException('Meeting recurrence occurrence count is invalid.');
        }
        if ($endsOn !== null && $occurrenceCount !== null) {
            throw new InvalidArgumentException('Meeting recurrence must use an end date or occurrence count, not both.');
        }
    }
}
