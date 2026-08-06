<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Optional\TimeTracking\Application\DTOs\BreakDailyAllocation;
use App\Modules\Optional\TimeTracking\Domain\Time\CalendarDayIntervalSplitter;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class BreakDailyAllocationCalculator
{
    public function __construct(private CalendarDayIntervalSplitter $splitter = new CalendarDayIntervalSplitter) {}

    /**
     * @return list<BreakDailyAllocation>
     */
    public function allocate(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, int $dailyLimitSeconds): array
    {
        if ($dailyLimitSeconds < 0) {
            throw new InvalidArgumentException('Break daily limit seconds cannot be negative.');
        }

        $allocations = [];

        foreach ($this->splitter->split($startsAt, $endsAt) as $slice) {
            $counted = min($slice->seconds, $dailyLimitSeconds);
            $excess = max(0, $slice->seconds - $dailyLimitSeconds);

            $allocations[] = new BreakDailyAllocation(
                calendarDate: $slice->calendarDate,
                breakSeconds: $slice->seconds,
                countedBreakSeconds: $counted,
                excessBreakSeconds: $excess,
                remainingSeconds: max(0, $dailyLimitSeconds - $slice->seconds),
            );
        }

        return $allocations;
    }
}
