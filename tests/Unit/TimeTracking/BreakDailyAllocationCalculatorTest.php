<?php

declare(strict_types=1);

namespace Tests\Unit\TimeTracking;

use App\Modules\Optional\TimeTracking\Application\BreakDailyAllocationCalculator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class BreakDailyAllocationCalculatorTest extends TestCase
{
    public function test_it_allocates_cross_midnight_breaks_by_warsaw_calendar_day(): void
    {
        $timezone = new DateTimeZone('Europe/Warsaw');
        $allocations = (new BreakDailyAllocationCalculator)->allocate(
            startsAt: new DateTimeImmutable('2026-08-01 23:45:00', $timezone),
            endsAt: new DateTimeImmutable('2026-08-02 00:20:00', $timezone),
            dailyLimitSeconds: 1800,
        );

        self::assertCount(2, $allocations);
        self::assertSame('2026-08-01', $allocations[0]->calendarDate);
        self::assertSame(900, $allocations[0]->breakSeconds);
        self::assertSame(900, $allocations[0]->countedBreakSeconds);
        self::assertSame(0, $allocations[0]->excessBreakSeconds);
        self::assertSame(900, $allocations[0]->remainingSeconds);
        self::assertSame('2026-08-02', $allocations[1]->calendarDate);
        self::assertSame(1200, $allocations[1]->breakSeconds);
        self::assertSame(1200, $allocations[1]->countedBreakSeconds);
        self::assertSame(0, $allocations[1]->excessBreakSeconds);
        self::assertSame(600, $allocations[1]->remainingSeconds);
    }

    public function test_it_marks_excess_break_time_per_calendar_day(): void
    {
        $timezone = new DateTimeZone('Europe/Warsaw');
        $allocations = (new BreakDailyAllocationCalculator)->allocate(
            startsAt: new DateTimeImmutable('2026-08-01 10:00:00', $timezone),
            endsAt: new DateTimeImmutable('2026-08-01 10:45:00', $timezone),
            dailyLimitSeconds: 1800,
        );

        self::assertCount(1, $allocations);
        self::assertSame(2700, $allocations[0]->breakSeconds);
        self::assertSame(1800, $allocations[0]->countedBreakSeconds);
        self::assertSame(900, $allocations[0]->excessBreakSeconds);
        self::assertSame(0, $allocations[0]->remainingSeconds);
    }
}
