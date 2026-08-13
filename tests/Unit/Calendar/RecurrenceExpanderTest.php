<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Modules\Core\Calendar\Domain\Events\CalendarAvailability;
use App\Modules\Core\Calendar\Domain\Events\PersonalCalendarEvent;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceExpander;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceFrequency;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RecurrenceExpanderTest extends TestCase
{
    public function test_daily_recurrence_preserves_warsaw_wall_time_across_dst_change(): void
    {
        $occurrences = (new RecurrenceExpander)->expand(
            $this->event(new RecurrenceRule(RecurrenceFrequency::Daily, occurrenceCount: 4)),
            new DateTimeImmutable('2026-03-27 00:00:00 Europe/Warsaw'),
            new DateTimeImmutable('2026-04-01 00:00:00 Europe/Warsaw'),
        );

        self::assertSame(
            ['2026-03-27T09:00:00+01:00', '2026-03-28T09:00:00+01:00', '2026-03-29T09:00:00+02:00', '2026-03-30T09:00:00+02:00'],
            array_map(static fn ($occurrence): string => $occurrence->startsAt->format('Y-m-d\TH:i:sP'), $occurrences),
        );
    }

    public function test_weekly_recurrence_honours_selected_weekdays_and_count(): void
    {
        $event = $this->event(new RecurrenceRule(RecurrenceFrequency::Weekly, weekdays: [1, 3], occurrenceCount: 4));
        $occurrences = (new RecurrenceExpander)->expand(
            $event,
            new DateTimeImmutable('2026-03-27 00:00:00 Europe/Warsaw'),
            new DateTimeImmutable('2026-04-20 00:00:00 Europe/Warsaw'),
        );

        self::assertSame(['2026-03-30', '2026-04-01', '2026-04-06', '2026-04-08'], array_column($occurrences, 'occurrenceDate'));
    }

    private function event(RecurrenceRule $recurrence): PersonalCalendarEvent
    {
        return new PersonalCalendarEvent(
            '01K00000000000000000000001',
            1,
            'Daily review',
            null,
            new DateTimeImmutable('2026-03-27 09:00:00 Europe/Warsaw'),
            new DateTimeImmutable('2026-03-27 10:00:00 Europe/Warsaw'),
            false,
            null,
            CalendarAvailability::Busy,
            $recurrence,
            [15],
            1,
        );
    }
}
