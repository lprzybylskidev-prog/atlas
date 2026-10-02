<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\MeetingStore;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecord;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use DateTimeImmutable;
use DateTimeZone;

final readonly class MeetingReminderDispatcher
{
    public function __construct(
        private MeetingStore $meetings,
        private ChatTransaction $transaction,
        private UserLookup $users,
        private NotificationPublisher $notifications,
    ) {}

    public function dispatch(DateTimeImmutable $now): int
    {
        $timezone = new DateTimeZone('Europe/Warsaw');
        $localNow = $now->setTimezone($timezone);
        $minute = $localNow->setTime((int) $localNow->format('H'), (int) $localNow->format('i'));
        $count = 0;

        foreach ($this->meetings->scheduled() as $meeting) {
            foreach ($this->dueOccurrences($meeting, $minute) as [$occurrenceDate, $minutesBefore]) {
                foreach ($this->meetings->invitations($meeting->id) as $invitation) {
                    if ($invitation->response !== MeetingResponse::Accepted) {
                        continue;
                    }
                    $recipient = $this->users->publicIdForInternalId($invitation->userId);
                    if ($recipient === null) {
                        continue;
                    }
                    $dispatched = $this->transaction->run(function () use ($meeting, $invitation, $occurrenceDate, $minutesBefore, $recipient): bool {
                        if (! $this->meetings->claimReminder($meeting->id, $invitation->userId, $occurrenceDate, $minutesBefore)) {
                            return false;
                        }
                        $this->notifications->publish(new CreateNotification(
                            type: 'chat.meeting.reminder',
                            title: 'notifications.meeting.reminder.title',
                            body: 'notifications.meeting.reminder.body',
                            recipientUserPublicId: $recipient,
                            deepLinkUrl: '/meetings/'.$meeting->publicId,
                            data: ['meeting' => $meeting->title, 'minutes' => $minutesBefore],
                            emailRequested: true,
                        ));
                        $this->meetings->markReminderDelivered($meeting->id, $invitation->userId, $occurrenceDate, $minutesBefore);

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

    /** @return list<array{string,int}> */
    private function dueOccurrences(MeetingRecord $meeting, DateTimeImmutable $minute): array
    {
        $timezone = new DateTimeZone('Europe/Warsaw');
        $start = $meeting->startsAt->setTimezone($timezone);
        $maxReminder = max($meeting->reminderMinutes === [] ? [0] : $meeting->reminderMinutes);
        $end = $minute->modify('+'.$maxReminder.' minutes')->modify('+1 minute');
        $cursor = $start->setTime(0, 0);
        if ($meeting->recurrence !== null && $meeting->recurrence->occurrenceCount === null && $cursor < $minute->setTime(0, 0)) {
            $cursor = $minute->setTime(0, 0);
        }
        $generated = 0;
        $examined = 0;
        $result = [];

        while ($cursor < $end && $generated < 500 && ($meeting->recurrence?->occurrenceCount !== null || $examined < 370)) {
            $examined++;
            if ($this->matches($meeting, $cursor, $start)) {
                $generated++;
                if ($meeting->recurrence?->occurrenceCount !== null && $generated > $meeting->recurrence->occurrenceCount) {
                    break;
                }
                if ($meeting->recurrence?->endsOn !== null && $cursor > $meeting->recurrence->endsOn->setTime(23, 59, 59)) {
                    break;
                }
                $occurrenceStart = new DateTimeImmutable($cursor->format('Y-m-d').' '.$start->format('H:i:s'), $timezone);
                $mutation = $this->mutation($meeting, $cursor->format('Y-m-d'));
                if ($mutation !== null && $mutation['cancelled']) {
                    $cursor = $cursor->modify('+1 day');

                    continue;
                }
                $value = $mutation['payload']['starts_at'] ?? null;
                if (is_string($value)) {
                    $occurrenceStart = new DateTimeImmutable($value);
                }
                foreach ($meeting->reminderMinutes as $minutesBefore) {
                    if ($occurrenceStart->modify(sprintf('-%d minutes', $minutesBefore))->format('Y-m-d H:i') === $minute->format('Y-m-d H:i')) {
                        $result[] = [$cursor->format('Y-m-d'), $minutesBefore];
                    }
                }
            }
            if ($meeting->recurrence === null) {
                break;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $result;
    }

    private function matches(MeetingRecord $meeting, DateTimeImmutable $day, DateTimeImmutable $start): bool
    {
        $recurrence = $meeting->recurrence;
        if ($recurrence === null) {
            return $day->format('Y-m-d') === $start->format('Y-m-d');
        }
        $days = (int) $start->setTime(0, 0)->diff($day)->format('%a');

        return match ($recurrence->frequency) {
            'daily' => true,
            'weekly' => intdiv($days, 7) >= 0 && in_array((int) $day->format('N'), $recurrence->weekdays === [] ? [(int) $start->format('N')] : $recurrence->weekdays, true),
            'monthly' => $day->format('j') === $start->format('j'),
            default => false,
        };
    }

    /** @return array{effective_date:string,scope:string,cancelled:bool,payload:array<string,mixed>}|null */
    private function mutation(MeetingRecord $meeting, string $date): ?array
    {
        $future = null;
        foreach ($this->meetings->mutations($meeting->id) as $mutation) {
            if ($mutation['scope'] === 'occurrence' && $mutation['effective_date'] === $date) {
                return $mutation;
            }
            if ($mutation['scope'] === 'future' && $mutation['effective_date'] <= $date) {
                $future = $mutation;
            }
        }

        return $future;
    }
}
