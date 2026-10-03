<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Optional\Chat\Application\Audit\ChatAuditEvents;
use App\Modules\Optional\Chat\Application\Contracts\CallStore;
use App\Modules\Optional\Chat\Application\Contracts\ChatRealtimePublisher;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\DTOs\CallHistoryEntry;
use App\Modules\Optional\Chat\Application\DTOs\CallParticipantRecord;
use App\Modules\Optional\Chat\Application\DTOs\CallPreferences;
use App\Modules\Optional\Chat\Application\DTOs\CallRecord;
use App\Modules\Optional\Chat\Application\DTOs\CallSnapshot;
use App\Modules\Optional\Chat\Application\DTOs\StartCallResult;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Calls\CallParticipantRole;
use App\Modules\Optional\Chat\Domain\Calls\CallParticipantState;
use App\Modules\Optional\Chat\Domain\Calls\CallStatus;
use App\Modules\Optional\Chat\Domain\Calls\Exceptions\CallOperationDenied;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;
use App\Modules\Optional\Chat\Domain\Conversations\TimelineEntryType;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use InvalidArgumentException;

final readonly class CallManager
{
    public function __construct(
        private CallStore $calls,
        private ConversationStore $conversations,
        private ConversationManager $conversationAccess,
        private ChatTransaction $transaction,
        private ChatModuleAccess $moduleAccess,
        private UserLookup $users,
        private ChatRealtimePublisher $realtime,
        private NotificationPublisher $notifications,
        private AuditRecorder $audit,
    ) {}

    public function start(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId, bool $cameraEnabled, string $clientRequestKey): StartCallResult
    {
        $this->moduleAccess->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::CALL_START);
        $this->ensureConversationAccess($userPublicId, $activeTeamPublicId, $conversationPublicId);
        $userId = $this->userId($userPublicId);
        $clientRequestKey = $this->requestKey($clientRequestKey);
        $requestHash = hash('sha256', $conversationPublicId.'|'.($cameraEnabled ? 'video' : 'audio'));

        $result = $this->transaction->run(function () use ($userId, $userPublicId, $activeTeamPublicId, $conversationPublicId, $cameraEnabled, $clientRequestKey, $requestHash): array {
            $this->calls->lockKey('chat:call:conversation:'.$conversationPublicId);
            $this->calls->lockKey('chat:rtc:user:'.$userId);
            $conversation = $this->conversations->findByPublicId($conversationPublicId, true) ?? throw CallOperationDenied::invalidConversation();
            $this->ensureAdHocConversation($conversation->type, $conversation->closed);
            $existingRequest = $this->calls->findByRequest($userId, $clientRequestKey);

            if ($existingRequest !== null) {
                if (! hash_equals($existingRequest->requestHash, $requestHash)) {
                    throw CallOperationDenied::idempotencyConflict();
                }

                return [$existingRequest, false, []];
            }

            $active = $this->calls->findActiveForConversation($conversation->id, true);

            if ($active !== null) {
                $participant = $this->calls->participant($active->id, $userId, true);

                if ($participant === null && $conversation->type === ConversationType::Team) {
                    if ($this->calls->activeJoinedForUser($userId, true) !== null) {
                        throw CallOperationDenied::busy();
                    }

                    $this->calls->addParticipant($active->id, $userId, CallParticipantRole::Joiner, CallParticipantState::Notified);
                } elseif ($participant === null) {
                    throw CallOperationDenied::notParticipant();
                }

                return [$active, false, []];
            }

            if ($this->calls->activeJoinedForUser($userId, true) !== null) {
                throw CallOperationDenied::busy();
            }

            $call = $this->calls->create($conversation->id, $userId, $clientRequestKey, $requestHash, $cameraEnabled);
            $this->calls->addParticipant($call->id, $userId, CallParticipantRole::Starter, CallParticipantState::Joined, $cameraEnabled, true);
            $alertUserIds = [];
            $availableInvitees = 0;

            foreach ($this->conversations->activeMemberships($conversation->id, true) as $membership) {
                if ($membership->userId === $userId) {
                    continue;
                }

                $state = $conversation->type === ConversationType::Team
                    ? CallParticipantState::Notified
                    : ($this->calls->activeJoinedForUser($membership->userId, true) === null ? CallParticipantState::Ringing : CallParticipantState::Busy);
                $this->calls->addParticipant($call->id, $membership->userId, CallParticipantRole::Invitee, $state);

                if ($state !== CallParticipantState::Busy) {
                    $availableInvitees++;
                    $alertUserIds[] = $membership->userId;
                }
            }

            if ($conversation->type === ConversationType::Team) {
                $this->calls->setStatus($call->id, CallStatus::Active, answered: true);
                $this->conversations->appendTimeline($conversation->id, TimelineEntryType::CallStarted, $userId, metadata: [
                    'call_public_id' => $call->publicId,
                    'initial_mode' => $cameraEnabled ? 'video' : 'audio',
                ]);
            } elseif ($availableInvitees === 0) {
                $starter = $this->calls->participant($call->id, $userId, true) ?? throw CallOperationDenied::notParticipant();
                $this->calls->setParticipantState($starter->id, CallParticipantState::Left);
                $this->calls->setStatus($call->id, CallStatus::Busy, ended: true);
                $this->conversations->appendTimeline($conversation->id, TimelineEntryType::CallBusy, $userId, metadata: ['call_public_id' => $call->publicId]);
            } else {
                $this->conversations->appendTimeline($conversation->id, TimelineEntryType::CallStarted, $userId, metadata: [
                    'call_public_id' => $call->publicId,
                    'initial_mode' => $cameraEnabled ? 'video' : 'audio',
                ]);
            }

            $this->audit($userPublicId, $call->publicId, ChatAuditEvents::CALL_STARTED, [
                'conversation_public_id' => $conversationPublicId,
                'initial_mode' => $cameraEnabled ? 'video' : 'audio',
                'team_public_id' => $activeTeamPublicId,
            ]);

            return [$this->calls->findByPublicId($call->publicId) ?? $call, true, $alertUserIds];
        });

        [$call, $created, $alertUserIds] = $result;
        $snapshot = $this->snapshot($call, $userId);

        if ($created) {
            $this->publishCallState($call, $alertUserIds, $snapshot->teamJoinStyle ? 'chat.call.available' : 'chat.call.incoming');
        }

        return new StartCallResult($snapshot, $created);
    }

    public function join(string $userPublicId, string $activeTeamPublicId, string $callPublicId, bool $cameraEnabled, bool $microphoneEnabled): CallSnapshot
    {
        $this->moduleAccess->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::CALL_JOIN);
        $userId = $this->userId($userPublicId);

        $call = $this->transaction->run(function () use ($userPublicId, $activeTeamPublicId, $callPublicId, $cameraEnabled, $microphoneEnabled, $userId): CallRecord {
            $this->calls->lockKey('chat:rtc:user:'.$userId);
            $call = $this->calls->findByPublicId($callPublicId, true) ?? throw CallOperationDenied::notFound();
            $conversation = $this->conversations->findById($call->conversationId) ?? throw CallOperationDenied::invalidConversation();
            $this->ensureConversationAccess($userPublicId, $activeTeamPublicId, $conversation->publicId);

            if (! $call->status->acceptsParticipants()) {
                throw CallOperationDenied::invalidTransition();
            }

            $active = $this->calls->activeJoinedForUser($userId, true);

            if ($active !== null && $active->callId !== $call->id) {
                $participant = $this->calls->participant($call->id, $userId, true);

                if ($participant !== null && $participant->state->canJoin()) {
                    $this->calls->setParticipantState($participant->id, CallParticipantState::Busy);
                }

                throw CallOperationDenied::busy();
            }

            $participant = $this->calls->participant($call->id, $userId, true);

            if ($participant === null && $conversation->type === ConversationType::Team) {
                $participant = $this->calls->addParticipant($call->id, $userId, CallParticipantRole::Joiner, CallParticipantState::Notified);
            }

            if ($participant === null) {
                throw CallOperationDenied::notParticipant();
            }

            if ($participant->state !== CallParticipantState::Joined) {
                if (! $participant->state->canJoin()) {
                    throw CallOperationDenied::invalidTransition();
                }

                $this->calls->setParticipantState($participant->id, CallParticipantState::Joined, $cameraEnabled, $microphoneEnabled);
            } else {
                $this->calls->setParticipantMedia($participant->id, $cameraEnabled, $microphoneEnabled);
            }

            if ($call->status === CallStatus::Ringing) {
                $this->calls->setStatus($call->id, CallStatus::Active, answered: true);
            }

            $this->audit($userPublicId, $call->publicId, ChatAuditEvents::CALL_JOINED, ['team_public_id' => $activeTeamPublicId]);

            return $this->calls->findByPublicId($call->publicId) ?? $call;
        });

        $snapshot = $this->snapshot($call, $userId);
        $this->publishCallState($call, $this->participantUserIds($call->id), 'chat.call.updated');

        return $snapshot;
    }

    public function markJoinFailed(string $userPublicId, string $callPublicId): void
    {
        $userId = $this->userId($userPublicId);
        $this->transaction->run(function () use ($callPublicId, $userId): void {
            $call = $this->calls->findByPublicId($callPublicId, true);

            if ($call === null) {
                return;
            }

            $participant = $this->calls->participant($call->id, $userId, true);

            if ($participant?->state === CallParticipantState::Joined) {
                $this->calls->setParticipantState($participant->id, CallParticipantState::Failed);
                if ($this->joinedParticipants($call->id) === [] && $call->status->acceptsParticipants()) {
                    $this->calls->setStatus($call->id, CallStatus::Failed, ended: true);
                    $this->conversations->appendTimeline($call->conversationId, TimelineEntryType::CallEnded, $userId, metadata: [
                        'call_public_id' => $call->publicId,
                        'result_status' => CallStatus::Failed->value,
                    ]);
                }
            }
        });
    }

    public function decline(string $userPublicId, string $activeTeamPublicId, string $callPublicId): CallSnapshot
    {
        $this->moduleAccess->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::CALL_DECLINE);
        $userId = $this->userId($userPublicId);
        $call = $this->transaction->run(function () use ($userPublicId, $activeTeamPublicId, $callPublicId, $userId): CallRecord {
            $call = $this->calls->findByPublicId($callPublicId, true) ?? throw CallOperationDenied::notFound();
            $participant = $this->calls->participant($call->id, $userId, true) ?? throw CallOperationDenied::notParticipant();

            if (! in_array($participant->state, [CallParticipantState::Ringing, CallParticipantState::Notified], true)) {
                throw CallOperationDenied::invalidTransition();
            }

            $this->calls->setParticipantState($participant->id, CallParticipantState::Declined);
            $this->finishIfNoPotentialParticipant($call, CallStatus::Declined);
            $this->audit($userPublicId, $call->publicId, ChatAuditEvents::CALL_DECLINED, ['team_public_id' => $activeTeamPublicId]);

            return $this->calls->findByPublicId($callPublicId) ?? $call;
        });
        $this->publishCallState($call, $this->participantUserIds($call->id), 'chat.call.updated');

        return $this->snapshot($call, $userId);
    }

    public function leave(string $userPublicId, string $activeTeamPublicId, string $callPublicId): CallSnapshot
    {
        $this->moduleAccess->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::CALL_LEAVE);
        $userId = $this->userId($userPublicId);
        [$call, $missedUserIds] = $this->transaction->run(function () use ($userPublicId, $activeTeamPublicId, $callPublicId, $userId): array {
            $call = $this->calls->findByPublicId($callPublicId, true) ?? throw CallOperationDenied::notFound();
            $participant = $this->calls->participant($call->id, $userId, true) ?? throw CallOperationDenied::notParticipant();

            if ($participant->state !== CallParticipantState::Joined) {
                throw CallOperationDenied::invalidTransition();
            }

            $this->calls->setParticipantState($participant->id, CallParticipantState::Left);
            $missedUserIds = [];

            if ($this->joinedParticipants($call->id) === []) {
                foreach ($this->calls->participants($call->id, true) as $candidate) {
                    if ($candidate->state === CallParticipantState::Ringing) {
                        $this->calls->setParticipantState($candidate->id, CallParticipantState::Missed);
                        $missedUserIds[] = $candidate->userId;
                    }
                }

                $status = $missedUserIds === [] ? CallStatus::Ended : CallStatus::Missed;
                $this->calls->setStatus($call->id, $status, ended: true);
                $this->conversations->appendTimeline($call->conversationId, $missedUserIds === [] ? TimelineEntryType::CallEnded : TimelineEntryType::CallMissed, $userId, metadata: [
                    'call_public_id' => $call->publicId,
                    'missed_count' => count($missedUserIds),
                ]);
                $this->audit($userPublicId, $call->publicId, ChatAuditEvents::CALL_ENDED, [
                    'team_public_id' => $activeTeamPublicId,
                    'result_status' => $status->value,
                ]);
            }

            $this->audit($userPublicId, $call->publicId, ChatAuditEvents::CALL_LEFT, ['team_public_id' => $activeTeamPublicId]);

            return [$this->calls->findByPublicId($callPublicId) ?? $call, $missedUserIds];
        });

        $this->publishCallState($call, $this->participantUserIds($call->id), 'chat.call.updated');
        $this->publishMissedNotifications($call, $missedUserIds);

        return $this->snapshot($call, $userId);
    }

    public function updateMedia(string $userPublicId, string $activeTeamPublicId, string $callPublicId, bool $cameraEnabled, bool $microphoneEnabled): CallSnapshot
    {
        $this->moduleAccess->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::CALL_MEDIA_UPDATE);
        $userId = $this->userId($userPublicId);
        $call = $this->transaction->run(function () use ($callPublicId, $userId, $cameraEnabled, $microphoneEnabled): CallRecord {
            $call = $this->calls->findByPublicId($callPublicId, true) ?? throw CallOperationDenied::notFound();
            $participant = $this->calls->participant($call->id, $userId, true) ?? throw CallOperationDenied::notParticipant();

            if ($participant->state !== CallParticipantState::Joined) {
                throw CallOperationDenied::invalidTransition();
            }

            $this->calls->setParticipantMedia($participant->id, $cameraEnabled, $microphoneEnabled);

            return $call;
        });
        $this->publishCallState($call, $this->participantUserIds($call->id), 'chat.call.updated');

        return $this->snapshot($call, $userId);
    }

    public function setScreenShare(string $userPublicId, string $activeTeamPublicId, string $callPublicId, bool $active): CallSnapshot
    {
        $this->moduleAccess->ensureAllowed(
            $userPublicId,
            $activeTeamPublicId,
            $active ? ChatPermissionCatalog::SCREEN_SHARE_STORE : ChatPermissionCatalog::SCREEN_SHARE_DESTROY,
        );
        $userId = $this->userId($userPublicId);
        $call = $this->transaction->run(function () use ($userPublicId, $activeTeamPublicId, $callPublicId, $userId, $active): CallRecord {
            $call = $this->calls->findByPublicId($callPublicId, true) ?? throw CallOperationDenied::notFound();
            $participant = $this->calls->participant($call->id, $userId, true) ?? throw CallOperationDenied::notParticipant();

            if ($participant->state !== CallParticipantState::Joined) {
                throw CallOperationDenied::invalidTransition();
            }

            if (! $this->calls->setScreenShare($participant->id, $active)) {
                throw CallOperationDenied::screenShareBusy();
            }

            $this->audit($userPublicId, $call->publicId, $active ? ChatAuditEvents::CALL_SCREEN_SHARE_STARTED : ChatAuditEvents::CALL_SCREEN_SHARE_STOPPED, ['team_public_id' => $activeTeamPublicId]);

            return $call;
        });
        $this->publishCallState($call, $this->participantUserIds($call->id), 'chat.call.updated');

        return $this->snapshot($call, $userId);
    }

    public function current(string $userPublicId, string $activeTeamPublicId): ?CallSnapshot
    {
        $this->moduleAccess->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::CALL_CURRENT);
        $userId = $this->userId($userPublicId);
        $participant = $this->calls->activeParticipantForUser($userId);

        if ($participant === null) {
            return null;
        }

        $call = $this->calls->findById($participant->callId);

        return $call === null ? null : $this->snapshot($call, $userId);
    }

    /** @return list<CallHistoryEntry> */
    public function history(string $userPublicId, string $activeTeamPublicId): array
    {
        $this->moduleAccess->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::CALL_INDEX);
        $userId = $this->userId($userPublicId);
        $entries = [];

        foreach ($this->calls->historyForUser($userId) as $row) {
            $call = $row['call'];
            $participant = $row['participant'];
            $conversation = $this->conversations->findById($call->conversationId);

            if ($conversation === null) {
                continue;
            }

            $start = $participant->joinedAt === null ? null : strtotime($participant->joinedAt);
            $end = $participant->leftAt === null ? ($call->endedAt === null ? null : strtotime($call->endedAt)) : strtotime($participant->leftAt);
            $entries[] = new CallHistoryEntry(
                publicId: $call->publicId,
                conversationLabel: $this->conversationLabel($conversation->id, $conversation->type, $conversation->name, $userId),
                conversationType: $conversation->type->value,
                direction: $call->startedByUserId === $userId ? 'outgoing' : 'incoming',
                initialMode: $call->initialCameraEnabled ? 'video' : 'audio',
                state: $participant->state->value,
                startedAt: $call->startedAt,
                answeredAt: $call->answeredAt,
                endedAt: $call->endedAt,
                durationSeconds: is_int($start) && is_int($end) ? max(0, $end - $start) : 0,
                canRejoin: $call->status->acceptsParticipants() && $participant->state->canJoin(),
            );
        }

        return $entries;
    }

    public function preferences(string $userPublicId, string $activeTeamPublicId): CallPreferences
    {
        $this->moduleAccess->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::CALL_PREFERENCES_SHOW);

        return $this->calls->preferences($this->userId($userPublicId));
    }

    public function savePreferences(string $userPublicId, string $activeTeamPublicId, CallPreferences $preferences): CallPreferences
    {
        $this->moduleAccess->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::CALL_PREFERENCES_UPDATE);
        $userId = $this->userId($userPublicId);
        $this->transaction->run(function () use ($userId, $preferences, $userPublicId, $activeTeamPublicId): void {
            $this->calls->savePreferences($userId, $preferences);
            $this->audit->record(new AuditEvent(
                module: 'chat',
                action: ChatAuditEvents::CALL_PREFERENCES_UPDATED,
                result: 'succeeded',
                source: 'application',
                actorPublicId: $userPublicId,
                targetType: 'user',
                targetPublicId: $userPublicId,
                aggregateType: 'user',
                aggregatePublicId: $userPublicId,
                after: ['team_public_id' => $activeTeamPublicId],
            ));
        });

        return $this->calls->preferences($userId);
    }

    private function snapshot(CallRecord $call, int $currentUserId): CallSnapshot
    {
        $conversation = $this->conversations->findById($call->conversationId) ?? throw CallOperationDenied::invalidConversation();
        $participant = $this->calls->participant($call->id, $currentUserId) ?? throw CallOperationDenied::notParticipant();
        $participants = $this->calls->participants($call->id);
        $summaries = $this->users->displaySummariesForInternalIds(array_map(static fn (CallParticipantRecord $row): int => $row->userId, $participants));
        $starter = $summaries[$call->startedByUserId] ?? null;
        $participantRows = [];

        foreach ($participants as $row) {
            $summary = $summaries[$row->userId] ?? null;
            $publicId = $summary !== null ? $summary->publicId : $this->users->publicIdForInternalId($row->userId);

            if ($publicId === null) {
                continue;
            }

            $participantRows[] = [
                'publicId' => $publicId,
                'name' => $summary !== null ? $summary->name : 'Atlas user',
                'state' => $row->state->value,
                'cameraEnabled' => $row->cameraEnabled,
                'microphoneEnabled' => $row->microphoneEnabled,
                'screenSharing' => $row->screenShareStartedAt !== null,
            ];
        }

        return new CallSnapshot(
            publicId: $call->publicId,
            conversationPublicId: $conversation->publicId,
            conversationType: $conversation->type,
            conversationLabel: $this->conversationLabel($conversation->id, $conversation->type, $conversation->name, $currentUserId),
            startedByUserPublicId: $starter !== null ? $starter->publicId : ($this->users->publicIdForInternalId($call->startedByUserId) ?? ''),
            startedByName: $starter !== null ? $starter->name : 'Atlas user',
            initialCameraEnabled: $call->initialCameraEnabled,
            status: $call->status,
            currentUserState: $participant->state,
            incoming: $call->startedByUserId !== $currentUserId && $participant->state === CallParticipantState::Ringing,
            teamJoinStyle: $conversation->type === ConversationType::Team,
            canRejoin: $call->status->acceptsParticipants() && $participant->state->canJoin(),
            startedAt: $call->startedAt,
            answeredAt: $call->answeredAt,
            endedAt: $call->endedAt,
            participants: $participantRows,
        );
    }

    private function ensureConversationAccess(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId): void
    {
        if (! $this->conversationAccess->canAccess($userPublicId, $activeTeamPublicId, $conversationPublicId)) {
            throw CallOperationDenied::notParticipant();
        }
    }

    private function ensureAdHocConversation(ConversationType $type, bool $closed): void
    {
        if ($closed || ! in_array($type, [ConversationType::Direct, ConversationType::Group, ConversationType::Team], true)) {
            throw CallOperationDenied::invalidConversation();
        }
    }

    private function userId(string $publicId): int
    {
        return $this->users->internalIdForPublicId($publicId) ?? throw CallOperationDenied::notParticipant();
    }

    private function requestKey(string $key): string
    {
        $key = trim($key);

        if ($key === '' || mb_strlen($key) > 120) {
            throw new InvalidArgumentException('Call client request key must contain between 1 and 120 characters.');
        }

        return $key;
    }

    /** @return list<int> */
    private function participantUserIds(int $callId): array
    {
        return array_map(static fn (CallParticipantRecord $participant): int => $participant->userId, $this->calls->participants($callId));
    }

    /** @return list<CallParticipantRecord> */
    private function joinedParticipants(int $callId): array
    {
        return array_values(array_filter($this->calls->participants($callId), static fn (CallParticipantRecord $participant): bool => $participant->state === CallParticipantState::Joined));
    }

    private function finishIfNoPotentialParticipant(CallRecord $call, CallStatus $status): void
    {
        $participants = $this->calls->participants($call->id, true);
        $otherPotential = array_filter($participants, static fn (CallParticipantRecord $participant): bool => $participant->role !== CallParticipantRole::Starter && in_array($participant->state, [CallParticipantState::Ringing, CallParticipantState::Notified, CallParticipantState::Joined], true));

        if ($otherPotential !== []) {
            return;
        }

        foreach ($participants as $participant) {
            if ($participant->state === CallParticipantState::Joined) {
                $this->calls->setParticipantState($participant->id, CallParticipantState::Left);
            }
        }

        $this->calls->setStatus($call->id, $status, ended: true);
        $this->conversations->appendTimeline($call->conversationId, TimelineEntryType::CallEnded, metadata: ['call_public_id' => $call->publicId, 'result' => $status->value]);
    }

    /** @param list<int> $userIds */
    private function publishCallState(CallRecord $call, array $userIds, string $event): void
    {
        foreach (array_unique($userIds) as $userId) {
            $publicId = $this->users->publicIdForInternalId($userId);

            if ($publicId !== null) {
                $this->realtime->user($publicId, $event, ['callPublicId' => $call->publicId]);
            }
        }
    }

    /** @param list<int> $userIds */
    private function publishMissedNotifications(CallRecord $call, array $userIds): void
    {
        $conversation = $this->conversations->findById($call->conversationId);

        if ($conversation === null) {
            return;
        }

        $starter = $this->users->displaySummariesForInternalIds([$call->startedByUserId])[$call->startedByUserId] ?? null;

        foreach ($userIds as $userId) {
            $publicId = $this->users->publicIdForInternalId($userId);

            if ($publicId === null) {
                continue;
            }

            $this->notifications->publish(new CreateNotification(
                type: 'chat.call.missed',
                title: 'Missed Call',
                body: 'You missed a Call in Atlas.',
                recipientUserPublicId: $publicId,
                teamPublicId: $conversation->type === ConversationType::Team ? $conversation->teamPublicId : null,
                severity: 'info',
                deepLinkUrl: '/user/calls',
                data: [
                    'title_key' => 'notifications.chat.call_missed.title',
                    'body_key' => 'notifications.chat.call_missed.body',
                    'caller' => $starter !== null ? $starter->name : 'Atlas user',
                ],
                emailRequested: false,
            ));
        }
    }

    private function conversationLabel(int $conversationId, ConversationType $type, ?string $name, int $currentUserId): string
    {
        if ($name !== null && $name !== '') {
            return $name;
        }

        if ($type === ConversationType::Direct) {
            foreach ($this->conversations->activeMemberships($conversationId) as $membership) {
                if ($membership->userId === $currentUserId) {
                    continue;
                }

                $summary = $this->users->displaySummariesForInternalIds([$membership->userId])[$membership->userId] ?? null;

                return $summary !== null ? $summary->name : 'Direct Call';
            }
        }

        return match ($type) {
            ConversationType::Group => 'Group Call',
            ConversationType::Team => 'Team Call',
            default => 'Call',
        };
    }

    /** @param array<string, mixed> $after */
    private function audit(string $actorPublicId, string $callPublicId, string $action, array $after): void
    {
        $this->audit->record(new AuditEvent(
            module: 'chat',
            action: $action,
            result: 'succeeded',
            source: 'application',
            actorPublicId: $actorPublicId,
            targetType: 'call',
            targetPublicId: $callPublicId,
            aggregateType: 'call',
            aggregatePublicId: $callPublicId,
            after: $after,
        ));
    }
}
