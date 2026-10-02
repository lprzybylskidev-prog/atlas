<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Files\Application\Public\DTOs\DownloadableFile;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Audit\ChatAuditEvents;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingStore;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecording;
use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecordingStatus;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;

final readonly class MeetingRecordingAccessManager
{
    public function __construct(
        private MeetingRecordingStore $recordings,
        private ChatTransaction $transaction,
        private ChatModuleAccess $access,
        private UserLookup $users,
        private FileStorage $files,
        private AuditRecorder $audit,
    ) {}

    /** @return array<string, mixed> */
    public function details(string $actor, string $team, string $recordingPublicId): array
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::RECORDING_SHOW);
        [$recording, $actorId, $participant] = $this->authorized($actor, $recordingPublicId);
        $shares = $participant ? $this->recordings->shares($recording->id) : [];
        $recipients = $this->users->displaySummariesForInternalIds(array_map(static fn ($share): int => $share->recipientUserId, $shares));

        return [
            'publicId' => $recording->publicId,
            'status' => $recording->status->value,
            'startedAt' => $recording->startedAt,
            'endedAt' => $recording->endedAt,
            'durationSeconds' => $recording->durationSeconds,
            'downloadUrl' => $recording->status === MeetingRecordingStatus::Ready ? '/meeting-recordings/'.$recording->publicId.'/download' : null,
            'canShare' => $participant && $this->access->allows($actor, $team, ChatPermissionCatalog::RECORDING_SHARE),
            'shares' => array_map(static fn ($share): array => [
                'publicId' => $share->publicId,
                'recipientPublicId' => $recipients[$share->recipientUserId]->publicId ?? '',
                'recipientName' => $recipients[$share->recipientUserId]->name ?? '',
            ], $shares),
        ];
    }

    public function downloadable(string $actor, string $team, string $recordingPublicId): DownloadableFile
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::RECORDING_DOWNLOAD);
        [$recording, $actorId] = $this->authorized($actor, $recordingPublicId);
        if ($recording->status !== MeetingRecordingStatus::Ready || $recording->filePublicId === null) {
            throw MeetingOperationDenied::rtcUnavailable();
        }

        return $this->files->cleanDownloadFile($recording->filePublicId, $actorId);
    }

    /** @return array{publicId:string} */
    public function share(string $actor, string $team, string $recordingPublicId, string $recipientPublicId): array
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::RECORDING_SHARE);
        $actorId = $this->userId($actor);
        $recipientId = $this->activeUserId($recipientPublicId);

        return $this->transaction->run(function () use ($recordingPublicId, $actor, $team, $actorId, $recipientId, $recipientPublicId): array {
            $recording = $this->recordings->find($recordingPublicId, true) ?? throw MeetingOperationDenied::notFound();
            if ($recording->status !== MeetingRecordingStatus::Ready || ! $this->recordings->participantHasAccess($recording->id, $actorId)) {
                throw MeetingOperationDenied::notInvited();
            }
            if ($recipientId === $actorId || $this->recordings->participantHasAccess($recording->id, $recipientId)) {
                throw MeetingOperationDenied::alreadyInvited();
            }
            $share = $this->recordings->share($recording->id, $recipientId, $actorId);
            $this->audit->record(new AuditEvent(
                module: 'chat', action: ChatAuditEvents::RECORDING_SHARED, result: 'succeeded', source: 'application',
                actorPublicId: $actor, targetType: 'meeting_recording', targetPublicId: $recording->publicId,
                aggregateType: 'meeting_recording', aggregatePublicId: $recording->publicId, teamPublicId: $team,
                after: ['recipient_public_id' => $recipientPublicId],
            ));

            return ['publicId' => $share->publicId];
        });
    }

    public function revoke(string $actor, string $team, string $recordingPublicId, string $sharePublicId): void
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::RECORDING_SHARE_REVOKE);
        $actorId = $this->userId($actor);
        $this->transaction->run(function () use ($recordingPublicId, $sharePublicId, $actor, $team, $actorId): void {
            $recording = $this->recordings->find($recordingPublicId, true) ?? throw MeetingOperationDenied::notFound();
            if (! $this->recordings->participantHasAccess($recording->id, $actorId) || ! $this->recordings->revokeShare($sharePublicId, $actorId)) {
                throw MeetingOperationDenied::notInvited();
            }
            $this->audit->record(new AuditEvent(
                module: 'chat', action: ChatAuditEvents::RECORDING_SHARE_REVOKED, result: 'succeeded', source: 'application',
                actorPublicId: $actor, targetType: 'meeting_recording', targetPublicId: $recording->publicId,
                aggregateType: 'meeting_recording', aggregatePublicId: $recording->publicId, teamPublicId: $team,
                after: ['share_public_id' => $sharePublicId],
            ));
        });
    }

    /** @return array{MeetingRecording,int,bool} */
    private function authorized(string $actor, string $recordingPublicId): array
    {
        $actorId = $this->userId($actor);
        $recording = $this->recordings->find($recordingPublicId) ?? throw MeetingOperationDenied::notFound();
        $participant = $this->recordings->participantHasAccess($recording->id, $actorId);
        if (! $participant && ! $this->recordings->sharedRecipientHasAccess($recording->id, $actorId)) {
            throw MeetingOperationDenied::notFound();
        }

        return [$recording, $actorId, $participant];
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
