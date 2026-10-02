<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Services;

use App\Modules\Core\Calendar\Application\Contracts\CalendarEventStore;
use App\Modules\Core\Calendar\Application\Contracts\CalendarPreferenceStore;
use App\Modules\Core\Calendar\Application\Contracts\CalendarReminderStore;
use App\Modules\Core\Calendar\Application\Contracts\CalendarTransaction;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CalendarReminderDispatcher
{
    private const MAX_REMINDER_MINUTES = 525600;

    public function __construct(
        private CalendarEventStore $events,
        private CalendarPreferenceStore $preferences,
        private CalendarReminderStore $deliveries,
        private CalendarTransaction $transaction,
        private PersonalCalendar $calendar,
        private UserLookup $users,
        private NotificationPublisher $notifications,
    ) {}

    public function dispatch(DateTimeImmutable $now): int
    {
        $timezone = new DateTimeZone('Europe/Warsaw');
        $localNow = $now->setTimezone($timezone);
        $minute = $localNow->setTime((int) $localNow->format('H'), (int) $localNow->format('i'));
        $count = 0;

        foreach ($this->events->ownerUserIds() as $userId) {
            $publicId = $this->users->publicIdForInternalId($userId);
            if ($publicId === null) {
                continue;
            }
            $preference = $this->preferences->forUser($userId);
            $rangeEnd = $minute->modify('+'.self::MAX_REMINDER_MINUTES.' minutes')->modify('+1 minute');

            foreach ($this->calendar->occurrences($userId, $minute, $rangeEnd) as $occurrence) {
                if ($occurrence->source !== 'personal') {
                    continue;
                }
                foreach ($occurrence->reminderMinutes as $minutesBefore) {
                    $target = (new DateTimeImmutable($occurrence->startsAt))->modify(sprintf('-%d minutes', $minutesBefore));
                    if ($target->format('Y-m-d H:i') !== $minute->format('Y-m-d H:i')) {
                        continue;
                    }
                    $dispatched = $this->transaction->run(function () use ($occurrence, $minutesBefore, $publicId, $preference): bool {
                        if (! $this->deliveries->claim($occurrence->eventPublicId, $occurrence->occurrenceDate, $minutesBefore)) {
                            return false;
                        }
                        $this->notifications->publish(new CreateNotification(
                            type: 'calendar.personal.reminder',
                            title: 'notifications.calendar.reminder.title',
                            body: 'notifications.calendar.reminder.body',
                            recipientUserPublicId: $publicId,
                            deepLinkUrl: '/calendar?view=day&date='.$occurrence->occurrenceDate,
                            data: ['event' => $occurrence->title, 'minutes' => $minutesBefore],
                            emailRequested: $preference->emailEnabled,
                        ));
                        $this->deliveries->delivered($occurrence->eventPublicId, $occurrence->occurrenceDate, $minutesBefore);

                        return true;
                    });
                    if ($dispatched) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}
