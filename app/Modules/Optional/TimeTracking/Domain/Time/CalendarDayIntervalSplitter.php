<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Domain\Time;

use App\Modules\Optional\TimeTracking\Domain\Exceptions\InvalidTimeInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CalendarDayIntervalSplitter
{
    private const BUSINESS_TIMEZONE = 'Europe/Warsaw';

    /**
     * @return list<CalendarDayIntervalSlice>
     */
    public function split(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array
    {
        if ($startsAt >= $endsAt) {
            throw InvalidTimeInterval::becauseStartMustPrecedeEnd($startsAt, $endsAt);
        }

        $timezone = new DateTimeZone(self::BUSINESS_TIMEZONE);
        $cursor = $startsAt->setTimezone($timezone);
        $localEnd = $endsAt->setTimezone($timezone);
        $slices = [];

        while ($cursor < $localEnd) {
            $nextDayStart = $cursor->setTime(0, 0)->modify('+1 day');
            $sliceEnd = $nextDayStart < $localEnd ? $nextDayStart : $localEnd;
            $seconds = $sliceEnd->getTimestamp() - $cursor->getTimestamp();

            if ($seconds > 0) {
                $slices[] = new CalendarDayIntervalSlice(
                    startsAt: $cursor,
                    endsAt: $sliceEnd,
                    calendarDate: $cursor->format('Y-m-d'),
                    seconds: $seconds,
                );
            }

            $cursor = $sliceEnd;
        }

        return $slices;
    }
}
