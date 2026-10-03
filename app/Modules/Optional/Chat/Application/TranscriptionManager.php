<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Audit\ChatAuditEvents;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingStore;
use App\Modules\Optional\Chat\Application\Contracts\TranscriptionProvider;
use App\Modules\Optional\Chat\Application\Contracts\TranscriptionStore;
use App\Modules\Optional\Chat\Application\DTOs\MeetingTranscription;
use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Meetings\TranscriptionStatus;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunner;
use RuntimeException;

final readonly class TranscriptionManager
{
    public function __construct(
        private TranscriptionStore $transcriptions,
        private MeetingRecordingStore $recordings,
        private TranscriptionProvider $provider,
        private ManagedProcessRunner $processes,
        private ChatTransaction $transaction,
        private ChatModuleAccess $access,
        private UserLookup $users,
        private AuditRecorder $audit,
        private ?ChatSearchProjectionUpdater $search = null,
    ) {}

    public function available(): bool
    {
        return $this->provider->available();
    }

    /** @return array<string, mixed> */
    public function request(string $actor, string $team, string $recordingPublicId): array
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::TRANSCRIPTION_STORE);
        if (! $this->provider->available()) {
            throw MeetingOperationDenied::transcriptionUnavailable();
        }
        $actorId = $this->userId($actor);

        /** @var array{MeetingTranscription,bool} $request */
        $request = $this->transaction->run(function () use ($recordingPublicId, $actorId, $actor, $team): array {
            $recording = $this->recordings->find($recordingPublicId, true) ?? throw MeetingOperationDenied::recordingRequired();
            if (! $this->recordings->eligibleForTranscription($recording->id)) {
                throw MeetingOperationDenied::recordingRequired();
            }
            if (! $this->recordings->participantHasAccess($recording->id, $actorId)) {
                throw MeetingOperationDenied::notInvited();
            }

            $existing = $this->transcriptions->forRecording($recording->id, true);
            if ($existing !== null && $existing->status !== TranscriptionStatus::Failed) {
                return [$existing, false];
            }
            if ($existing !== null) {
                $this->transcriptions->prepareRetry($existing->id, $existing->publicId, $this->provider->key());
                $transcription = $this->transcriptions->find($existing->publicId, true) ?? throw new RuntimeException('Meeting transcription is missing.');
            } else {
                $transcription = $this->transcriptions->create($recording->id, $actorId, $this->provider->key());
                $this->transcriptions->queue($transcription->id, $transcription->publicId);
                $transcription = $this->transcriptions->find($transcription->publicId, true) ?? throw new RuntimeException('Meeting transcription is missing.');
            }

            $this->audit->record(new AuditEvent(
                module: 'chat', action: ChatAuditEvents::TRANSCRIPTION_REQUESTED, result: 'succeeded', source: 'application',
                actorPublicId: $actor, targetType: 'meeting_transcription', targetPublicId: $transcription->publicId,
                aggregateType: 'meeting_recording', aggregatePublicId: $recording->publicId, teamPublicId: $team,
                metadata: ['provider' => $this->provider->key()],
            ));

            return [$transcription, true];
        });
        [$transcription, $shouldDispatch] = $request;

        if ($transcription->status === TranscriptionStatus::Completed) {
            return $this->view($actor, $team, $transcription->publicId);
        }
        if ($shouldDispatch) {
            try {
                $run = $this->processes->start(
                    TranscriptionProcess::KEY,
                    'application',
                    ['transcription_public_id' => $transcription->publicId],
                    $actor,
                    $team,
                    $transcription->publicId,
                );
                $this->transcriptions->queue($transcription->id, $run);
            } catch (\Throwable $exception) {
                $this->transcriptions->fail($transcription->id, 'queue_failed');
                throw $exception;
            }
        }

        return $this->view($actor, $team, $transcription->publicId);
    }

    /** @return array<string, mixed> */
    public function forRecording(string $actor, string $team, string $recordingPublicId): ?array
    {
        if (! $this->provider->available()) {
            return null;
        }
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::TRANSCRIPTION_SHOW);
        $recording = $this->recordings->find($recordingPublicId);
        if ($recording === null || ! $this->recordings->eligibleForTranscription($recording->id)) {
            return null;
        }
        $actorId = $this->userId($actor);
        if (! $this->recordings->participantHasAccess($recording->id, $actorId)) {
            return null;
        }
        $transcription = $this->transcriptions->forRecording($recording->id);

        return $transcription === null
            ? ['eligible' => true, 'canRequest' => $this->access->allows($actor, $team, ChatPermissionCatalog::TRANSCRIPTION_STORE), 'transcription' => null]
            : ['eligible' => true, 'canRequest' => $this->access->allows($actor, $team, ChatPermissionCatalog::TRANSCRIPTION_STORE), 'transcription' => $this->view($actor, $team, $transcription->publicId)];
    }

    /** @return array<string, mixed> */
    public function view(string $actor, string $team, string $transcriptionPublicId): array
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::TRANSCRIPTION_SHOW);
        [$transcription, $actorId, $participant] = $this->authorized($actor, $transcriptionPublicId);
        $versions = $participant ? $this->transcriptions->versions($transcription->id) : [];
        $shares = $participant ? $this->transcriptions->shares($transcription->id) : [];
        $displayIds = array_values(array_unique([
            ...array_values(array_filter(array_map(static fn ($version): ?int => $version->createdByUserId, $versions))),
            ...array_map(static fn ($share): int => $share->recipientUserId, $shares),
        ]));
        $people = $this->users->displaySummariesForInternalIds($displayIds);

        return [
            'publicId' => $transcription->publicId,
            'status' => $transcription->status->value,
            'text' => $transcription->currentText,
            'segments' => $transcription->segments,
            'failureCode' => $transcription->failureCode,
            'version' => $versions === [] ? 0 : end($versions)->version,
            'canEdit' => $participant && $transcription->status === TranscriptionStatus::Completed && $this->access->allows($actor, $team, ChatPermissionCatalog::TRANSCRIPTION_UPDATE),
            'canShare' => $participant && $transcription->status === TranscriptionStatus::Completed && $this->access->allows($actor, $team, ChatPermissionCatalog::TRANSCRIPTION_SHARE),
            'history' => array_map(static fn ($version): array => [
                'publicId' => $version->publicId,
                'version' => $version->version,
                'source' => $version->source,
                'editorName' => $version->createdByUserId === null ? null : ($people[$version->createdByUserId]->name ?? null),
                'text' => $version->text,
                'createdAt' => $version->createdAt,
            ], $versions),
            'shares' => array_map(static fn ($share): array => [
                'publicId' => $share->publicId,
                'recipientPublicId' => $people[$share->recipientUserId]->publicId ?? '',
                'recipientName' => $people[$share->recipientUserId]->name ?? '',
            ], $shares),
        ];
    }

    /** @return array<string, mixed> */
    public function edit(string $actor, string $team, string $transcriptionPublicId, int $expectedVersion, string $text): array
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::TRANSCRIPTION_UPDATE);
        $actorId = $this->userId($actor);
        $this->transaction->run(function () use ($actor, $team, $actorId, $transcriptionPublicId, $expectedVersion, $text): void {
            $transcription = $this->transcriptions->find($transcriptionPublicId, true) ?? throw MeetingOperationDenied::notFound();
            if ($transcription->status !== TranscriptionStatus::Completed || ! $this->transcriptions->participantHasAccess($transcription->id, $actorId)) {
                throw MeetingOperationDenied::transcriptNotReady();
            }
            $this->transcriptions->edit($transcription->id, $expectedVersion, $actorId, $text);
            $this->search?->refresh('transcript', $transcription->publicId);
            $this->audit->record(new AuditEvent(
                module: 'chat', action: ChatAuditEvents::TRANSCRIPT_EDITED, result: 'succeeded', source: 'application',
                actorPublicId: $actor, targetType: 'meeting_transcription', targetPublicId: $transcription->publicId,
                aggregateType: 'meeting_transcription', aggregatePublicId: $transcription->publicId, teamPublicId: $team,
                metadata: ['version' => $expectedVersion + 1],
            ));
        });

        return $this->view($actor, $team, $transcriptionPublicId);
    }

    /** @return array{publicId:string} */
    public function share(string $actor, string $team, string $transcriptionPublicId, string $recipientPublicId): array
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::TRANSCRIPTION_SHARE);
        $actorId = $this->userId($actor);
        $recipientId = $this->activeUserId($recipientPublicId);

        return $this->transaction->run(function () use ($actor, $team, $actorId, $recipientId, $recipientPublicId, $transcriptionPublicId): array {
            $transcription = $this->transcriptions->find($transcriptionPublicId, true) ?? throw MeetingOperationDenied::notFound();
            if ($transcription->status !== TranscriptionStatus::Completed || ! $this->transcriptions->participantHasAccess($transcription->id, $actorId)) {
                throw MeetingOperationDenied::notInvited();
            }
            if ($recipientId === $actorId || $this->transcriptions->participantHasAccess($transcription->id, $recipientId)) {
                throw MeetingOperationDenied::alreadyInvited();
            }
            $share = $this->transcriptions->share($transcription->id, $recipientId, $actorId);
            $this->audit->record(new AuditEvent(
                module: 'chat', action: ChatAuditEvents::TRANSCRIPT_SHARED, result: 'succeeded', source: 'application',
                actorPublicId: $actor, targetType: 'meeting_transcription', targetPublicId: $transcription->publicId,
                aggregateType: 'meeting_transcription', aggregatePublicId: $transcription->publicId, teamPublicId: $team,
                metadata: ['recipient_public_id' => $recipientPublicId],
            ));

            return ['publicId' => $share->publicId];
        });
    }

    public function revoke(string $actor, string $team, string $transcriptionPublicId, string $sharePublicId): void
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::TRANSCRIPTION_SHARE);
        $actorId = $this->userId($actor);
        $this->transaction->run(function () use ($actor, $team, $actorId, $transcriptionPublicId, $sharePublicId): void {
            $transcription = $this->transcriptions->find($transcriptionPublicId, true) ?? throw MeetingOperationDenied::notFound();
            if (! $this->transcriptions->participantHasAccess($transcription->id, $actorId) || ! $this->transcriptions->revokeShare($sharePublicId, $actorId)) {
                throw MeetingOperationDenied::notInvited();
            }
            $this->audit->record(new AuditEvent(
                module: 'chat', action: ChatAuditEvents::TRANSCRIPT_SHARE_REVOKED, result: 'succeeded', source: 'application',
                actorPublicId: $actor, targetType: 'meeting_transcription', targetPublicId: $transcription->publicId,
                aggregateType: 'meeting_transcription', aggregatePublicId: $transcription->publicId, teamPublicId: $team,
                metadata: ['share_public_id' => $sharePublicId],
            ));
        });
    }

    /** @return array{MeetingTranscription,int,bool} */
    private function authorized(string $actor, string $transcriptionPublicId): array
    {
        $actorId = $this->userId($actor);
        $transcription = $this->transcriptions->find($transcriptionPublicId) ?? throw MeetingOperationDenied::notFound();
        $participant = $this->transcriptions->participantHasAccess($transcription->id, $actorId);
        if (! $participant && ! $this->transcriptions->sharedRecipientHasAccess($transcription->id, $actorId)) {
            throw MeetingOperationDenied::notFound();
        }

        return [$transcription, $actorId, $participant];
    }

    private function activeUserId(string $publicId): int
    {
        foreach ($this->users->allActiveDisplaySummaries() as $user) {
            if ($user->publicId === $publicId) {
                return $this->userId($publicId);
            }
        }

        throw MeetingOperationDenied::notFound();
    }

    private function userId(string $publicId): int
    {
        return $this->users->internalIdForPublicId($publicId) ?? throw MeetingOperationDenied::notFound();
    }
}
