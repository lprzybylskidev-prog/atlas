<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Services;

use App\Modules\Core\Calendar\Application\Contracts\CalendarEventStore;
use App\Modules\Core\Calendar\Application\Contracts\CalendarPreferenceStore;
use App\Modules\Core\Calendar\Application\Contracts\CalendarTransaction;
use App\Modules\Core\Calendar\Application\DTOs\CalendarEventInput;
use App\Modules\Core\Calendar\Application\DTOs\CalendarOccurrenceView;
use App\Modules\Core\Calendar\Application\DTOs\CalendarPreference;
use App\Modules\Core\Calendar\Application\Exceptions\CalendarEventNotFound;
use App\Modules\Core\Calendar\Application\Exceptions\StaleCalendarEvent;
use App\Modules\Core\Calendar\Domain\Events\CalendarOccurrence;
use App\Modules\Core\Calendar\Domain\Events\PersonalCalendarEvent;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceExpander;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceRule;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

final readonly class PersonalCalendar
{
    private const TIMEZONE = 'Europe/Warsaw';

    public function __construct(
        private CalendarEventStore $events,
        private CalendarPreferenceStore $preferences,
        private CalendarTransaction $transaction,
        private RecurrenceExpander $recurrence,
    ) {}

    /** @return list<CalendarOccurrenceView> */
    public function occurrences(int $ownerUserId, DateTimeImmutable $rangeStartsAt, DateTimeImmutable $rangeEndsAt): array
    {
        if ($rangeEndsAt <= $rangeStartsAt) {
            throw new InvalidArgumentException('Calendar range end must be after its start.');
        }

        $events = $this->events->forOwnerBefore($ownerUserId, $rangeEndsAt);
        $overrides = $this->events->occurrenceOverrides(array_map(
            static fn (PersonalCalendarEvent $event): string => $event->publicId,
            $events,
        ));
        $views = [];

        foreach ($events as $event) {
            foreach ($this->recurrence->expand($event, $rangeStartsAt, $rangeEndsAt) as $occurrence) {
                $overrideExists = array_key_exists($occurrence->occurrenceDate, $overrides[$event->publicId] ?? []);
                $override = $overrides[$event->publicId][$occurrence->occurrenceDate] ?? null;

                if ($overrideExists && $override === null) {
                    continue;
                }

                $views[] = $this->view(
                    $event,
                    $override !== null
                        ? new CalendarOccurrence(
                            $event->publicId,
                            $occurrence->occurrenceDate,
                            $override->startsAt,
                            $override->endsAt,
                        )
                        : $occurrence,
                    $override,
                );
            }
        }

        usort($views, static fn (CalendarOccurrenceView $left, CalendarOccurrenceView $right): int => [
            $left->startsAt,
            $left->title,
        ] <=> [
            $right->startsAt,
            $right->title,
        ]);

        return $views;
    }

    public function create(int $ownerUserId, CalendarEventInput $input): string
    {
        $publicId = (string) new Ulid;
        $this->transaction->run(fn () => $this->events->create($publicId, $ownerUserId, $input));

        return $publicId;
    }

    public function update(
        int $ownerUserId,
        string $eventPublicId,
        string $occurrenceDate,
        string $scope,
        int $expectedVersion,
        CalendarEventInput $input,
    ): void {
        $event = $this->ownedEvent($eventPublicId, $ownerUserId);
        $this->assertVersion($event, $expectedVersion);

        $this->transaction->run(function () use ($event, $occurrenceDate, $scope, $input): void {
            if ($event->recurrence === null || $scope === 'series') {
                if (! $this->events->update($event->publicId, $event->ownerUserId, $event->version, $input)) {
                    throw new StaleCalendarEvent;
                }

                return;
            }

            if ($scope === 'occurrence') {
                $this->events->upsertOccurrenceOverride(
                    $event->publicId,
                    $event->ownerUserId,
                    $occurrenceDate,
                    $this->withoutRecurrence($input),
                );

                return;
            }

            if ($scope !== 'future') {
                throw new InvalidArgumentException('Unknown recurring Calendar mutation scope.');
            }

            if (! $this->events->splitSeries(
                $event,
                $occurrenceDate,
                (string) new Ulid,
                $input,
            )) {
                throw new StaleCalendarEvent;
            }
        });
    }

    public function delete(
        int $ownerUserId,
        string $eventPublicId,
        string $occurrenceDate,
        string $scope,
        int $expectedVersion,
    ): void {
        $event = $this->ownedEvent($eventPublicId, $ownerUserId);
        $this->assertVersion($event, $expectedVersion);

        $this->transaction->run(function () use ($event, $occurrenceDate, $scope): void {
            if ($event->recurrence === null || $scope === 'series') {
                if (! $this->events->delete($event->publicId, $event->ownerUserId, $event->version)) {
                    throw new StaleCalendarEvent;
                }

                return;
            }

            if ($scope === 'occurrence') {
                $this->events->upsertOccurrenceOverride($event->publicId, $event->ownerUserId, $occurrenceDate, null);

                return;
            }

            if ($scope !== 'future') {
                throw new InvalidArgumentException('Unknown recurring Calendar mutation scope.');
            }

            $previousDay = (new DateTimeImmutable($occurrenceDate, new DateTimeZone(self::TIMEZONE)))->modify('-1 day');
            $truncated = $this->withRecurrence($event, new RecurrenceRule(
                frequency: $event->recurrence->frequency,
                interval: $event->recurrence->interval,
                weekdays: $event->recurrence->weekdays,
                endsOn: $previousDay,
            ));

            if (! $this->events->update($event->publicId, $event->ownerUserId, $event->version, $truncated)) {
                throw new StaleCalendarEvent;
            }
        });
    }

    public function preference(int $userId): CalendarPreference
    {
        return $this->preferences->forUser($userId);
    }

    public function savePreference(int $userId, CalendarPreference $preference): void
    {
        $this->transaction->run(fn () => $this->preferences->save($userId, $preference));
    }

    private function ownedEvent(string $eventPublicId, int $ownerUserId): PersonalCalendarEvent
    {
        return $this->events->findOwned($eventPublicId, $ownerUserId) ?? throw CalendarEventNotFound::ownedEvent();
    }

    private function assertVersion(PersonalCalendarEvent $event, int $expectedVersion): void
    {
        if ($event->version !== $expectedVersion) {
            throw new StaleCalendarEvent;
        }
    }

    private function withoutRecurrence(CalendarEventInput $input): CalendarEventInput
    {
        return new CalendarEventInput(
            $input->title,
            $input->description,
            $input->startsAt,
            $input->endsAt,
            $input->allDay,
            $input->location,
            $input->availability,
            null,
            $input->reminderMinutes,
        );
    }

    private function withRecurrence(PersonalCalendarEvent $event, RecurrenceRule $recurrence): CalendarEventInput
    {
        return new CalendarEventInput(
            $event->title,
            $event->description,
            $event->startsAt,
            $event->endsAt,
            $event->allDay,
            $event->location,
            $event->availability,
            $recurrence,
            $event->reminderMinutes,
        );
    }

    private function view(
        PersonalCalendarEvent $event,
        CalendarOccurrence $occurrence,
        ?CalendarEventInput $override,
    ): CalendarOccurrenceView {
        $timezone = new DateTimeZone(self::TIMEZONE);

        return new CalendarOccurrenceView(
            eventPublicId: $event->publicId,
            occurrenceDate: $occurrence->occurrenceDate,
            title: $override instanceof CalendarEventInput ? $override->title : $event->title,
            description: $override instanceof CalendarEventInput ? $override->description : $event->description,
            startsAt: $occurrence->startsAt->setTimezone($timezone)->format('Y-m-d\TH:i:sP'),
            endsAt: $occurrence->endsAt->setTimezone($timezone)->format('Y-m-d\TH:i:sP'),
            allDay: $override instanceof CalendarEventInput ? $override->allDay : $event->allDay,
            location: $override instanceof CalendarEventInput ? $override->location : $event->location,
            availability: ($override instanceof CalendarEventInput ? $override->availability : $event->availability)->value,
            recurring: $event->recurrence !== null,
            recurrenceFrequency: $event->recurrence?->frequency->value,
            recurrenceInterval: $event->recurrence === null ? 1 : $event->recurrence->interval,
            recurrenceWeekdays: $event->recurrence === null ? [] : $event->recurrence->weekdays,
            recurrenceEndsOn: $event->recurrence?->endsOn?->format('Y-m-d'),
            recurrenceCount: $event->recurrence?->occurrenceCount,
            reminderMinutes: $override instanceof CalendarEventInput ? $override->reminderMinutes : $event->reminderMinutes,
            version: $event->version,
        );
    }
}
