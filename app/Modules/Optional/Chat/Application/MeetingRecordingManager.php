<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Audit\ChatAuditEvents;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingAssembler;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingStore;
use App\Modules\Optional\Chat\Application\Contracts\MeetingStore;
use App\Modules\Optional\Chat\Application\Contracts\RtcGateway;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecord;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecording;
use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Conversations\TimelineEntryType;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecordingSegmentStatus;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecordingStatus;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use Throwable;

final readonly class MeetingRecordingManager
{
    public function __construct(
        private MeetingStore $meetings,
        private MeetingRecordingStore $recordings,
        private ConversationStore $conversations,
        private ChatTransaction $transaction,
        private ChatModuleAccess $access,
        private UserLookup $users,
        private RtcGateway $gateway,
        private MeetingRecordingAssembler $assembler,
        private AuditRecorder $audit,
    ) {}

    /** @return array<string, scalar|null> */
    public function state(string $actor, string $team, string $meetingPublicId, string $occurrenceDate): array
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::RECORDING_STATE);
        $actorId = $this->userId($actor);
        $meeting = $this->authorizedMeeting($meetingPublicId, $actorId, false);
        if (! $meeting->mode->hasRtc()) {
            throw MeetingOperationDenied::rtcUnavailable();
        }
        $session = $this->meetings->rtcSession($meeting, $occurrenceDate);
        if ($session === null) {
            return $this->view(null);
        }

        return $this->view($this->recordings->forOccurrence($session->occurrenceId));
    }

    /** @return array<string, scalar|null> */
    public function control(string $actor, string $team, string $meetingPublicId, string $occurrenceDate, string $action): array
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::RECORDING_MANAGE);
        $actorId = $this->userId($actor);

        return match ($action) {
            'start' => $this->start($actorId, $meetingPublicId, $occurrenceDate),
            'pause' => $this->pauseOrStop($actorId, $meetingPublicId, $occurrenceDate, true),
            'resume' => $this->resume($actorId, $meetingPublicId, $occurrenceDate),
            'stop' => $this->pauseOrStop($actorId, $meetingPublicId, $occurrenceDate, false),
            default => throw MeetingOperationDenied::invalidRtcAction(),
        };
    }

    /** @return array<string, scalar|null> */
    private function start(int $actorId, string $meetingPublicId, string $date): array
    {
        [$meeting, $recording, $room] = $this->transaction->run(function () use ($actorId, $meetingPublicId, $date): array {
            $meeting = $this->organizerMeeting($meetingPublicId, $actorId);
            $session = $this->meetings->rtcSession($meeting, $date, true);
            if ($session === null || $session->roomName === '' || $session->endedAt !== null || $this->recordings->forOccurrence($session->occurrenceId, true) !== null) {
                throw MeetingOperationDenied::rtcUnavailable();
            }

            return [$meeting, $this->recordings->create($session->occurrenceId, $actorId), $session->roomName];
        });

        try {
            $started = $this->gateway->startRoomCompositeRecording($room, $recording->publicId, 1);
        } catch (Throwable $exception) {
            $this->transaction->run(fn () => $this->recordings->setStatus($recording->id, MeetingRecordingStatus::Failed, 'egress_start_failed'));
            throw $exception;
        }

        try {
            return $this->transaction->run(function () use ($meeting, $recording, $started, $actorId, $date): array {
                $this->recordings->createSegment($recording->id, $started->egressId, $started->stagingPath);
                $this->recordings->setStatus($recording->id, MeetingRecordingStatus::Recording);
                $this->timeline($meeting, TimelineEntryType::MeetingRecordingStarted, $actorId, $recording, $date);

                return $this->view($this->recordings->forOccurrence($recording->occurrenceId));
            });
        } catch (Throwable $exception) {
            $this->stopOrphanedEgress($started->egressId, $started->stagingPath);
            $this->transaction->run(fn () => $this->recordings->setStatus($recording->id, MeetingRecordingStatus::Failed, 'recording_persistence_failed'));

            throw $exception;
        }
    }

    /** @return array<string, scalar|null> */
    private function resume(int $actorId, string $meetingPublicId, string $date): array
    {
        [$meeting, $recording, $room, $sequence] = $this->transaction->run(function () use ($actorId, $meetingPublicId, $date): array {
            $meeting = $this->organizerMeeting($meetingPublicId, $actorId);
            $session = $this->meetings->rtcSession($meeting, $date, true);
            $recording = $session === null ? null : $this->recordings->forOccurrence($session->occurrenceId, true);
            if ($session === null || $session->roomName === '' || $session->endedAt !== null || $recording?->status !== MeetingRecordingStatus::Paused) {
                throw MeetingOperationDenied::rtcUnavailable();
            }
            $this->recordings->setStatus($recording->id, MeetingRecordingStatus::Resuming);

            return [$meeting, $recording, $session->roomName, $this->recordings->nextSegmentSequence($recording->id)];
        });

        try {
            $started = $this->gateway->startRoomCompositeRecording($room, $recording->publicId, $sequence);
        } catch (Throwable $exception) {
            $this->transaction->run(fn () => $this->recordings->setStatus($recording->id, MeetingRecordingStatus::Paused, 'egress_resume_failed'));
            throw $exception;
        }

        try {
            return $this->transaction->run(function () use ($meeting, $recording, $started, $actorId, $date): array {
                $this->recordings->createSegment($recording->id, $started->egressId, $started->stagingPath);
                $this->recordings->setStatus($recording->id, MeetingRecordingStatus::Recording);
                $this->timeline($meeting, TimelineEntryType::MeetingRecordingResumed, $actorId, $recording, $date);

                return $this->view($this->recordings->forOccurrence($recording->occurrenceId));
            });
        } catch (Throwable $exception) {
            $this->stopOrphanedEgress($started->egressId, $started->stagingPath);
            $this->transaction->run(fn () => $this->recordings->setStatus($recording->id, MeetingRecordingStatus::Paused, 'recording_persistence_failed'));

            throw $exception;
        }
    }

    /** @return array<string, scalar|null> */
    private function pauseOrStop(int $actorId, string $meetingPublicId, string $date, bool $pause): array
    {
        [$meeting, $recording, $segment] = $this->transaction->run(function () use ($actorId, $meetingPublicId, $date, $pause): array {
            $meeting = $this->organizerMeeting($meetingPublicId, $actorId);
            $session = $this->meetings->rtcSession($meeting, $date, true);
            $recording = $session === null ? null : $this->recordings->forOccurrence($session->occurrenceId, true);
            $allowed = $pause
                ? $recording?->status === MeetingRecordingStatus::Recording
                : in_array($recording?->status, [MeetingRecordingStatus::Recording, MeetingRecordingStatus::Paused], true);
            if (! $allowed) {
                throw MeetingOperationDenied::rtcUnavailable();
            }
            $segment = $this->recordings->activeSegment($recording->id, true);
            if ($segment === null && $recording->status !== MeetingRecordingStatus::Paused) {
                throw MeetingOperationDenied::rtcUnavailable();
            }
            $this->recordings->setStatus($recording->id, $pause ? MeetingRecordingStatus::Pausing : MeetingRecordingStatus::Stopping);
            if ($segment !== null) {
                $this->recordings->setSegmentStatus($segment->id, MeetingRecordingSegmentStatus::Stopping);
            }

            return [$meeting, $recording, $segment];
        });

        try {
            if ($segment !== null) {
                $this->gateway->stopRoomCompositeRecording($segment->egressId);
            }
        } catch (Throwable $exception) {
            $this->transaction->run(function () use ($recording, $segment): void {
                $this->recordings->setSegmentStatus($segment->id, MeetingRecordingSegmentStatus::Recording);
                $this->recordings->setStatus($recording->id, MeetingRecordingStatus::Recording, 'egress_stop_failed');
            });
            throw $exception;
        }

        return $this->transaction->run(function () use ($meeting, $recording, $segment, $actorId, $date, $pause): array {
            if ($segment !== null) {
                $this->recordings->setSegmentStatus($segment->id, MeetingRecordingSegmentStatus::Processing);
            }
            $this->recordings->setStatus($recording->id, $pause ? MeetingRecordingStatus::Paused : MeetingRecordingStatus::Processing);
            $this->timeline($meeting, $pause ? TimelineEntryType::MeetingRecordingPaused : TimelineEntryType::MeetingRecordingStopped, $actorId, $recording, $date);

            return $this->view($this->recordings->forOccurrence($recording->occurrenceId));
        });
    }

    private function organizerMeeting(string $publicId, int $actorId): MeetingRecord
    {
        $meeting = $this->authorizedMeeting($publicId, $actorId, true);
        if ($meeting->organizerUserId !== $actorId || ! $meeting->mode->hasRtc() || $meeting->status->value === 'cancelled') {
            throw MeetingOperationDenied::organizerOnly();
        }

        return $meeting;
    }

    private function authorizedMeeting(string $publicId, int $actorId, bool $lock): MeetingRecord
    {
        $meeting = $this->meetings->find($publicId, $lock) ?? throw MeetingOperationDenied::notFound();
        if ($this->meetings->invitation($meeting->id, $actorId, $lock) === null) {
            throw MeetingOperationDenied::notInvited();
        }

        return $meeting;
    }

    private function timeline(MeetingRecord $meeting, TimelineEntryType $type, int $actorId, MeetingRecording $recording, string $date): void
    {
        $conversation = $this->conversations->findByPublicId($meeting->conversationPublicId, true) ?? throw MeetingOperationDenied::notFound();
        $this->conversations->appendTimeline($conversation->id, $type, $actorId, metadata: ['recording_public_id' => $recording->publicId, 'occurrence_date' => $date]);
        $action = match ($type) {
            TimelineEntryType::MeetingRecordingStarted => ChatAuditEvents::RECORDING_STARTED,
            TimelineEntryType::MeetingRecordingPaused => ChatAuditEvents::RECORDING_PAUSED,
            TimelineEntryType::MeetingRecordingResumed => ChatAuditEvents::RECORDING_RESUMED,
            TimelineEntryType::MeetingRecordingStopped => ChatAuditEvents::RECORDING_STOPPED,
            default => throw new \LogicException('Unsupported Meeting recording timeline event.'),
        };
        $this->audit->record(new AuditEvent(
            module: 'chat',
            action: $action,
            result: 'succeeded',
            source: 'application',
            actorPublicId: $this->users->publicIdForInternalId($actorId),
            targetType: 'meeting_recording',
            targetPublicId: $recording->publicId,
            aggregateType: 'meeting',
            aggregatePublicId: $meeting->publicId,
            after: ['occurrence_date' => $date],
        ));
    }

    /** @return array<string, scalar|null> */
    private function view(?MeetingRecording $recording): array
    {
        return [
            'publicId' => $recording?->publicId,
            'status' => $recording?->status->value ?? 'not_recording',
            'startedAt' => $recording?->startedAt,
            'endedAt' => $recording?->endedAt,
            'durationSeconds' => $recording?->durationSeconds,
            'ready' => $recording?->status === MeetingRecordingStatus::Ready,
        ];
    }

    private function userId(string $publicId): int
    {
        return $this->users->internalIdForPublicId($publicId) ?? throw MeetingOperationDenied::notFound();
    }

    private function stopOrphanedEgress(string $egressId, string $stagingPath): void
    {
        try {
            $this->gateway->stopRoomCompositeRecording($egressId);
        } catch (Throwable) {
            // The original persistence failure remains authoritative; Egress staging is non-canonical.
        }
        $this->assembler->cleanup([$stagingPath], '');
    }
}
