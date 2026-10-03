<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Calendar\Application\Public\Contracts\FreeBusyLookup;
use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventContribution;
use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventMutation;
use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventRecurrence;
use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyQuery;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Optional\Chat\Application\Audit\ChatAuditEvents;
use App\Modules\Optional\Chat\Application\Calendar\MeetingCalendarPublisher;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\MeetingStore;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInput;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInvitationRecord;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecord;
use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMutationScope;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRole;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

final readonly class MeetingManager
{
    public function __construct(
        private MeetingStore $meetings,
        private ConversationManager $conversations,
        private ChatTransaction $transaction,
        private ChatModuleAccess $access,
        private UserLookup $users,
        private MeetingCalendarPublisher $calendar,
        private FreeBusyLookup $freeBusy,
        private NotificationPublisher $notifications,
        private AuditRecorder $audit,
    ) {}

    /** @return array{meeting: MeetingRecord, conflicts: array<string,int>} */
    public function create(string $actorPublicId, string $teamPublicId, MeetingInput $input): array
    {
        $this->access->ensureAllowed($actorPublicId, $teamPublicId, ChatPermissionCatalog::MEETING_STORE);
        $organizerId = $this->activeUserId($actorPublicId);
        $invitees = array_values(array_diff($input->inviteePublicIds, [$actorPublicId]));
        $inviteeIds = $this->activeUserIds($invitees);
        $participantPublicIds = array_values(array_unique([$actorPublicId, ...$invitees]));
        $conflicts = $this->conflictMap($participantPublicIds, $input->startsAt, $input->endsAt);
        $publicId = (string) new Ulid;

        $meeting = $this->transaction->run(function () use ($publicId, $actorPublicId, $organizerId, $input, $inviteeIds): MeetingRecord {
            $conversation = $this->conversations->ensureMeetingConversation($publicId, $input->recurrence === null ? null : $publicId, $actorPublicId, $input->title);
            $meeting = $this->meetings->create($publicId, $organizerId, $conversation->publicId, $input);
            $this->meetings->invite($meeting->id, $organizerId, $organizerId, MeetingRole::Organizer, MeetingResponse::Accepted);
            foreach ($inviteeIds as $publicId => $userId) {
                $this->meetings->invite($meeting->id, $userId, $organizerId, MeetingRole::Participant, MeetingResponse::Pending);
                $this->conversations->inviteMeetingParticipant($conversation->publicId, $actorPublicId, $publicId);
            }
            $this->recordAudit($actorPublicId, $meeting, ChatAuditEvents::MEETING_CREATED, ['mode' => $meeting->mode->value]);

            return $meeting;
        });
        $this->publishCalendar($meeting);
        $this->notifyInvitees($meeting, $actorPublicId, $invitees, 'chat.meeting.invitation');

        return ['meeting' => $meeting, 'conflicts' => $conflicts];
    }

    /** @return list<array<string,mixed>> */
    public function listFor(string $actorPublicId, string $teamPublicId): array
    {
        $this->access->ensureAllowed($actorPublicId, $teamPublicId, ChatPermissionCatalog::MEETING_INDEX);
        $userId = $this->activeUserId($actorPublicId);
        $users = $this->users->displaySummariesForInternalIds(array_values(array_unique(array_merge([$userId], array_map(
            static fn (MeetingRecord $meeting): int => $meeting->organizerUserId, $this->meetings->forUser($userId),
        )))));

        return array_map(function (MeetingRecord $meeting) use ($userId, $users): array {
            $invitation = $this->meetings->invitation($meeting->id, $userId);

            return $this->view($meeting, $invitation, $users[$meeting->organizerUserId]->name ?? '');
        }, $this->meetings->forUser($userId));
    }

    /** @return array<string,mixed> */
    public function show(string $actorPublicId, string $teamPublicId, string $meetingPublicId): array
    {
        $this->access->ensureAllowed($actorPublicId, $teamPublicId, ChatPermissionCatalog::MEETING_SHOW);
        $actorId = $this->activeUserId($actorPublicId);
        $meeting = $this->authorized($meetingPublicId, $actorId);
        $invitations = $this->meetings->invitations($meeting->id);
        $summaries = $this->users->displaySummariesForInternalIds(array_map(static fn (MeetingInvitationRecord $item): int => $item->userId, $invitations));

        $attendance = $this->meetings->attendance($meeting);
        $attendanceUsers = $this->users->displaySummariesForInternalIds(array_values(array_unique(array_map(static fn (array $item): int => $item['userId'], $attendance))));

        $occurrenceDate = $meeting->startsAt->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format('Y-m-d');
        $rtcSession = $this->meetings->rtcSession($meeting, $occurrenceDate);
        $currentRtcParticipant = $rtcSession === null ? null : array_find($rtcSession->participants, static fn (array $participant): bool => $participant['userId'] === $actorId);

        return [...$this->view($meeting, $this->meetings->invitation($meeting->id, $actorId), $summaries[$meeting->organizerUserId]->name ?? ''),
            'participants' => array_map(static fn (MeetingInvitationRecord $item): array => [
                'publicId' => $summaries[$item->userId]->publicId ?? '', 'name' => $summaries[$item->userId]->name ?? '',
                'role' => $item->role->value, 'response' => $item->response->value,
            ], $invitations),
            'attendance' => array_map(static fn (array $item): array => ['publicId' => $attendanceUsers[$item['userId']]->publicId ?? '', 'name' => $attendanceUsers[$item['userId']]->name ?? '', ...$item], $attendance),
            'canRejoinOnline' => $rtcSession !== null && $rtcSession->endedAt === null && $rtcSession->locked === false && $currentRtcParticipant !== null && $currentRtcParticipant['bannedAt'] === null];
    }

    public function invite(string $actorPublicId, string $teamPublicId, string $meetingPublicId, string $inviteePublicId): void
    {
        $this->access->ensureAllowed($actorPublicId, $teamPublicId, ChatPermissionCatalog::MEETING_INVITATION_STORE);
        $actorId = $this->activeUserId($actorPublicId);
        $inviteeId = $this->activeUserId($inviteePublicId);
        $meeting = $this->transaction->run(function () use ($meetingPublicId, $actorId, $inviteeId, $actorPublicId, $inviteePublicId): MeetingRecord {
            $meeting = $this->authorized($meetingPublicId, $actorId, true);
            $occurrenceDate = $meeting->startsAt->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format('Y-m-d');
            $session = $this->meetings->rtcSession($meeting, $occurrenceDate, true);
            if ($session?->locked === true) {
                throw MeetingOperationDenied::locked();
            }
            if ($this->meetings->invitation($meeting->id, $inviteeId, true) !== null) {
                throw MeetingOperationDenied::alreadyInvited();
            }
            $this->meetings->invite($meeting->id, $inviteeId, $actorId, MeetingRole::Participant, MeetingResponse::Pending);
            $this->conversations->inviteMeetingParticipant($meeting->conversationPublicId, $actorPublicId, $inviteePublicId);
            $this->recordAudit($actorPublicId, $meeting, ChatAuditEvents::MEETING_PARTICIPANT_INVITED, ['participant_public_id' => $inviteePublicId]);

            return $meeting;
        });
        $this->publishCalendar($meeting);
        $this->notifyInvitees($meeting, $actorPublicId, [$inviteePublicId], 'chat.meeting.invitation');
    }

    public function respond(string $actorPublicId, string $teamPublicId, string $meetingPublicId, MeetingResponse $response): void
    {
        $this->access->ensureAllowed($actorPublicId, $teamPublicId, ChatPermissionCatalog::MEETING_RESPONSE_UPDATE);
        $userId = $this->activeUserId($actorPublicId);
        $meeting = $this->transaction->run(function () use ($meetingPublicId, $userId, $response, $actorPublicId): MeetingRecord {
            $meeting = $this->authorized($meetingPublicId, $userId, true);
            $invitation = $this->meetings->invitation($meeting->id, $userId, true) ?? throw MeetingOperationDenied::notInvited();
            $this->meetings->respond($invitation->id, $response);
            $this->conversations->changeMeetingResponse($meeting->conversationPublicId, $actorPublicId, $response);

            return $meeting;
        });
        $this->publishCalendar($meeting);
    }

    public function remove(string $actorPublicId, string $teamPublicId, string $meetingPublicId, string $participantPublicId): void
    {
        $this->access->ensureAllowed($actorPublicId, $teamPublicId, ChatPermissionCatalog::MEETING_MODERATE);
        $actorId = $this->activeUserId($actorPublicId);
        $participantId = $this->activeUserId($participantPublicId);
        $meeting = $this->transaction->run(function () use ($meetingPublicId, $actorId, $participantId, $actorPublicId, $participantPublicId): MeetingRecord {
            $meeting = $this->organizerMeeting($meetingPublicId, $actorId, true);
            $invitation = $this->meetings->invitation($meeting->id, $participantId, true) ?? throw MeetingOperationDenied::notInvited();
            if ($invitation->role === MeetingRole::Organizer) {
                throw MeetingOperationDenied::organizerCannotBeRemoved();
            }
            $this->meetings->remove($invitation->id, $actorId);
            $this->conversations->removeMeetingParticipant($meeting->conversationPublicId, $actorPublicId, $participantPublicId);
            $this->recordAudit($actorPublicId, $meeting, ChatAuditEvents::MEETING_PARTICIPANT_REMOVED, ['participant_public_id' => $participantPublicId]);

            return $meeting;
        });
        $this->publishCalendar($meeting);
    }

    public function update(string $actorPublicId, string $teamPublicId, string $meetingPublicId, MeetingInput $input, MeetingMutationScope $scope, string $occurrenceDate): void
    {
        $this->access->ensureAllowed($actorPublicId, $teamPublicId, ChatPermissionCatalog::MEETING_UPDATE);
        $actorId = $this->activeUserId($actorPublicId);
        $meeting = $this->transaction->run(function () use ($meetingPublicId, $actorId, $input, $scope, $occurrenceDate, $actorPublicId): MeetingRecord {
            $meeting = $this->organizerMeeting($meetingPublicId, $actorId, true);
            $this->meetings->update($meeting, $input, $scope, $occurrenceDate);
            if ($scope === MeetingMutationScope::Series || $meeting->recurrence === null) {
                $this->conversations->renameMeetingConversation($meeting->conversationPublicId, $input->title);
            }
            $this->recordAudit($actorPublicId, $meeting, ChatAuditEvents::MEETING_UPDATED, ['scope' => $scope->value]);

            return $this->meetings->find($meetingPublicId) ?? $meeting;
        });
        $this->publishCalendar($meeting);
        $this->notifyAll($meeting, $actorPublicId, 'chat.meeting.updated');
    }

    public function cancel(string $actorPublicId, string $teamPublicId, string $meetingPublicId, MeetingMutationScope $scope, string $occurrenceDate): void
    {
        $this->access->ensureAllowed($actorPublicId, $teamPublicId, ChatPermissionCatalog::MEETING_CANCEL);
        $actorId = $this->activeUserId($actorPublicId);
        $meeting = $this->transaction->run(function () use ($meetingPublicId, $actorId, $scope, $occurrenceDate, $actorPublicId): MeetingRecord {
            $meeting = $this->organizerMeeting($meetingPublicId, $actorId, true);
            $this->meetings->cancel($meeting, $scope, $occurrenceDate);
            $this->recordAudit($actorPublicId, $meeting, ChatAuditEvents::MEETING_CANCELLED, ['scope' => $scope->value]);

            return $this->meetings->find($meetingPublicId) ?? $meeting;
        });
        $this->publishCalendar($meeting);
        $this->notifyAll($meeting, $actorPublicId, 'chat.meeting.cancelled');
    }

    private function publishCalendar(MeetingRecord $meeting): void
    {
        $participants = array_values(array_filter(array_map(fn (MeetingInvitationRecord $i): ?string => $this->users->publicIdForInternalId($i->userId), $this->meetings->invitations($meeting->id))));
        $this->calendar->upsert(new CalendarEventContribution('chat', $meeting->publicId, $meeting->title, $meeting->startsAt, $meeting->endsAt, false, $participants,
            $meeting->description, $meeting->location, $meeting->recurrence === null ? null : new CalendarEventRecurrence($meeting->recurrence->frequency, $meeting->recurrence->weekdays, $meeting->recurrence->endsOn, $meeting->recurrence->occurrenceCount),
            'meeting', $meeting->mode->value, '/meetings/'.$meeting->publicId, $meeting->status->value === 'cancelled',
            array_map(static function (array $mutation): CalendarEventMutation {
                $payload = $mutation['payload'];

                return new CalendarEventMutation(
                    effectiveDate: $mutation['effective_date'], scope: $mutation['scope'], cancelled: $mutation['cancelled'],
                    title: is_string($payload['title'] ?? null) ? $payload['title'] : null,
                    description: is_string($payload['description'] ?? null) ? $payload['description'] : null,
                    startsAt: is_string($payload['starts_at'] ?? null) ? new DateTimeImmutable($payload['starts_at']) : null,
                    endsAt: is_string($payload['ends_at'] ?? null) ? new DateTimeImmutable($payload['ends_at']) : null,
                    location: is_string($payload['location'] ?? null) ? $payload['location'] : null,
                    mode: is_string($payload['mode'] ?? null) ? $payload['mode'] : null,
                );
            }, $this->meetings->mutations($meeting->id)),
        ));
    }

    private function authorized(string $publicId, int $userId, bool $lock = false): MeetingRecord
    {
        $meeting = $this->meetings->find($publicId, $lock) ?? throw MeetingOperationDenied::notFound();
        if ($this->meetings->invitation($meeting->id, $userId, $lock) === null) {
            throw MeetingOperationDenied::notInvited();
        }

        return $meeting;
    }

    private function organizerMeeting(string $publicId, int $userId, bool $lock = false): MeetingRecord
    {
        $meeting = $this->authorized($publicId, $userId, $lock);
        if ($meeting->organizerUserId !== $userId) {
            throw MeetingOperationDenied::organizerOnly();
        }

        return $meeting;
    }

    private function activeUserId(string $publicId): int
    {
        return $this->activeUserIds([$publicId])[$publicId];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    private function activeUserIds(array $ids): array
    {
        $active = array_fill_keys(array_map(static fn ($u): string => $u->publicId, $this->users->allActiveDisplaySummaries()), true);
        foreach ($ids as $id) {
            if (! isset($active[$id])) {
                throw MeetingOperationDenied::notFound();
            }
        }
        $mapped = $this->users->internalIdsForPublicIds($ids);
        if (count($mapped) !== count($ids)) {
            throw MeetingOperationDenied::notFound();
        }

        return $mapped;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    private function conflictMap(array $ids, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $out = [];
        foreach ($this->freeBusy->conflicts(new FreeBusyQuery($ids, $start, $end)) as $c) {
            $out[$c->userPublicId] = $c->busyWindowCount;
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function view(MeetingRecord $m, ?MeetingInvitationRecord $i, string $organizer): array
    {
        return ['publicId' => $m->publicId, 'conversationPublicId' => $m->conversationPublicId, 'title' => $m->title, 'description' => $m->description, 'startsAt' => $m->startsAt->format(DATE_ATOM), 'endsAt' => $m->endsAt->format(DATE_ATOM), 'mode' => $m->mode->value, 'location' => $m->location, 'status' => $m->status->value, 'recurring' => $m->recurrence !== null, 'recurrenceFrequency' => $m->recurrence?->frequency, 'recurrenceWeekdays' => $m->recurrence === null ? [] : $m->recurrence->weekdays, 'recurrenceEndsOn' => $m->recurrence?->endsOn?->format('Y-m-d'), 'recurrenceCount' => $m->recurrence?->occurrenceCount, 'reminderMinutes' => $m->reminderMinutes, 'organizer' => $organizer, 'role' => $i?->role->value, 'response' => $i?->response->value, 'canJoinOnline' => $m->mode->hasRtc() && $m->status->value !== 'cancelled', 'version' => $m->version];
    }

    /** @param list<string> $recipients */
    private function notifyInvitees(MeetingRecord $m, string $actor, array $recipients, string $type): void
    {
        foreach ($recipients as $id) {
            if ($id !== $actor) {
                $this->notifications->publish(new CreateNotification($type, 'notifications.meeting.'.substr($type, strrpos($type, '.') + 1).'.title', 'notifications.meeting.'.substr($type, strrpos($type, '.') + 1).'.body', $id, null, 'info', '/meetings/'.$m->publicId, ['meeting' => $m->title], true));
            }
        }
    }

    private function notifyAll(MeetingRecord $m, string $actor, string $type): void
    {
        $ids = array_values(array_filter(array_map(fn ($i) => $this->users->publicIdForInternalId($i->userId), $this->meetings->invitations($m->id))));
        $this->notifyInvitees($m, $actor, $ids, $type);
    }

    /** @param array<string,mixed> $after */
    private function recordAudit(string $actor, MeetingRecord $m, string $action, array $after): void
    {
        $this->audit->record(new AuditEvent(module: 'chat', action: $action, result: 'succeeded', source: 'application', actorPublicId: $actor, targetType: 'meeting', targetPublicId: $m->publicId, aggregateType: 'meeting', aggregatePublicId: $m->publicId, after: $after));
    }
}
