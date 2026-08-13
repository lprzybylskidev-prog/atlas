<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Domain\Events;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class RecurrenceExpander
{
    private const TIMEZONE = 'Europe/Warsaw';

    /** @return list<CalendarOccurrence> */
    public function expand(
        PersonalCalendarEvent $event,
        DateTimeImmutable $rangeStartsAt,
        DateTimeImmutable $rangeEndsAt,
    ): array {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $eventStart = $event->startsAt->setTimezone($timezone);
        $eventEnd = $event->endsAt->setTimezone($timezone);
        $durationSeconds = $event->endsAt->getTimestamp() - $event->startsAt->getTimestamp();
        $durationDays = max(1, (int) $eventStart->setTime(0, 0)->diff($eventEnd->setTime(0, 0))->format('%a'));

        if ($event->recurrence === null) {
            return $event->endsAt > $rangeStartsAt && $event->startsAt < $rangeEndsAt
                ? [new CalendarOccurrence($event->publicId, $eventStart->format('Y-m-d'), $event->startsAt, $event->endsAt)]
                : [];
        }

        $occurrences = [];
        $cursor = $eventStart->setTime(0, 0);
        $seriesStartDay = $cursor;
        $generated = 0;
        $iterations = 0;
        $hardEnd = $rangeEndsAt->setTimezone($timezone)->modify('+1 day')->setTime(0, 0);

        while ($cursor < $hardEnd && $iterations < 40000) {
            $iterations++;

            if ($event->recurrence->endsOn !== null && $cursor > $event->recurrence->endsOn->setTimezone($timezone)->setTime(23, 59, 59)) {
                break;
            }

            if ($this->matches($cursor, $seriesStartDay, $event->recurrence, (int) $eventStart->format('N'))) {
                $generated++;

                if ($event->recurrence->occurrenceCount !== null && $generated > $event->recurrence->occurrenceCount) {
                    break;
                }

                $start = new DateTimeImmutable(
                    $cursor->format('Y-m-d').' '.$eventStart->format('H:i:s.u'),
                    $timezone,
                );
                $end = $event->allDay
                    ? $start->modify(sprintf('+%d days', $durationDays))
                    : $start->add(new DateInterval('PT'.$durationSeconds.'S'));

                if ($end > $rangeStartsAt && $start < $rangeEndsAt) {
                    $occurrences[] = new CalendarOccurrence($event->publicId, $cursor->format('Y-m-d'), $start, $end);
                }
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $occurrences;
    }

    private function matches(
        DateTimeImmutable $day,
        DateTimeImmutable $seriesStartDay,
        RecurrenceRule $rule,
        int $startWeekday,
    ): bool {
        if ($day < $seriesStartDay) {
            return false;
        }

        $days = (int) $seriesStartDay->diff($day)->format('%a');

        return match ($rule->frequency) {
            RecurrenceFrequency::Daily => $days % $rule->interval === 0,
            RecurrenceFrequency::Weekly => intdiv($days, 7) % $rule->interval === 0
                && in_array((int) $day->format('N'), $rule->weekdays === [] ? [$startWeekday] : $rule->weekdays, true),
            RecurrenceFrequency::Monthly => $this->matchesMonthly($day, $seriesStartDay, $rule->interval),
        };
    }

    private function matchesMonthly(DateTimeImmutable $day, DateTimeImmutable $seriesStartDay, int $interval): bool
    {
        $months = (((int) $day->format('Y') - (int) $seriesStartDay->format('Y')) * 12)
            + ((int) $day->format('n') - (int) $seriesStartDay->format('n'));

        return $months >= 0
            && $months % $interval === 0
            && $day->format('j') === $seriesStartDay->format('j');
    }
}
