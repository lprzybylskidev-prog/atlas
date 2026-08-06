<?php

declare(strict_types=1);

namespace Tests\Unit\TimeTracking;

use App\Modules\Optional\TimeTracking\Domain\Exceptions\InvalidTimeInterval;
use App\Modules\Optional\TimeTracking\Domain\Time\CalendarDayIntervalSplitter;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class CalendarDayIntervalSplitterTest extends TestCase
{
    public function test_it_keeps_single_day_intervals_as_exact_seconds(): void
    {
        $timezone = new DateTimeZone('Europe/Warsaw');
        $slices = (new CalendarDayIntervalSplitter)->split(
            new DateTimeImmutable('2026-08-01 08:00:00', $timezone),
            new DateTimeImmutable('2026-08-01 09:01:05', $timezone),
        );

        self::assertCount(1, $slices);
        self::assertSame('2026-08-01', $slices[0]->calendarDate);
        self::assertSame(3665, $slices[0]->seconds);
    }

    public function test_it_splits_cross_midnight_intervals_by_warsaw_calendar_day(): void
    {
        $timezone = new DateTimeZone('Europe/Warsaw');
        $slices = (new CalendarDayIntervalSplitter)->split(
            new DateTimeImmutable('2026-08-01 22:30:00', $timezone),
            new DateTimeImmutable('2026-08-02 01:15:00', $timezone),
        );

        self::assertCount(2, $slices);
        self::assertSame('2026-08-01', $slices[0]->calendarDate);
        self::assertSame(5400, $slices[0]->seconds);
        self::assertSame('2026-08-02', $slices[1]->calendarDate);
        self::assertSame(4500, $slices[1]->seconds);
    }

    public function test_it_uses_real_elapsed_seconds_across_daylight_saving_start(): void
    {
        $timezone = new DateTimeZone('Europe/Warsaw');
        $slices = (new CalendarDayIntervalSplitter)->split(
            new DateTimeImmutable('2026-03-29 00:30:00', $timezone),
            new DateTimeImmutable('2026-03-30 00:30:00', $timezone),
        );

        self::assertCount(2, $slices);
        self::assertSame('2026-03-29', $slices[0]->calendarDate);
        self::assertSame(81000, $slices[0]->seconds);
        self::assertSame('2026-03-30', $slices[1]->calendarDate);
        self::assertSame(1800, $slices[1]->seconds);
    }

    public function test_it_rejects_empty_or_reversed_intervals(): void
    {
        $timezone = new DateTimeZone('Europe/Warsaw');

        $this->expectException(InvalidTimeInterval::class);

        (new CalendarDayIntervalSplitter)->split(
            new DateTimeImmutable('2026-08-01 08:00:00', $timezone),
            new DateTimeImmutable('2026-08-01 08:00:00', $timezone),
        );
    }
}
