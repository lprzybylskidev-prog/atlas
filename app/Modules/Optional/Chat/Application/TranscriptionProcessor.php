<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Audit\ChatAuditEvents;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingStore;
use App\Modules\Optional\Chat\Application\Contracts\TranscriptionProvider;
use App\Modules\Optional\Chat\Application\Contracts\TranscriptionStore;
use App\Modules\Optional\Chat\Application\DTOs\MeetingTranscription;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionResult;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionSource;
use App\Modules\Optional\Chat\Domain\Meetings\TranscriptionStatus;
use App\Modules\Optional\Chat\Infrastructure\Runtime\ContinueTranscriptionJob;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessReporter;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunInspector;
use RuntimeException;
use Throwable;

final readonly class TranscriptionProcessor
{
    public function __construct(
        private TranscriptionStore $transcriptions,
        private MeetingRecordingStore $recordings,
        private TranscriptionProvider $provider,
        private FileStorage $files,
        private UserLookup $users,
        private ManagedProcessRunInspector $runs,
        private ManagedProcessReporter $reporter,
        private ChatTransaction $transaction,
        private AuditRecorder $audit,
        private ?ChatSearchProjectionUpdater $search = null,
    ) {}

    public function process(string $runPublicId): void
    {
        $input = $this->runs->inputSnapshot($runPublicId);
        $publicId = $input['transcription_public_id'] ?? null;
        if (! is_string($publicId)) {
            throw new RuntimeException('Transcription process input is invalid.');
        }
        $transcription = $this->transcriptions->find($publicId);
        if ($transcription === null) {
            $this->reporter->succeeded($runPublicId, 'removed', 0, 0, 'Meeting transcript was removed with its recording');

            return;
        }
        if ($transcription->status === TranscriptionStatus::Completed) {
            $this->reporter->succeeded($runPublicId, 'completed', 1, 1, 'Meeting transcription completed', ['completed' => 1]);

            return;
        }
        if ($transcription->status === TranscriptionStatus::Failed) {
            $this->transaction->run(function () use ($transcription, $runPublicId): void {
                $current = $this->transcriptions->find($transcription->publicId, true);
                if ($current?->status === TranscriptionStatus::Failed) {
                    $this->transcriptions->prepareRetry($current->id, $runPublicId, $this->provider->key());
                }
            });
            $transcription = $this->transcriptions->find($publicId)
                ?? throw new RuntimeException('Meeting transcription was removed while retrying.');
        }
        if ($transcription->managedProcessRunPublicId !== null
            && $transcription->managedProcessRunPublicId !== $transcription->publicId
            && $transcription->managedProcessRunPublicId !== $runPublicId) {
            $this->reporter->succeeded($runPublicId, 'superseded', 0, 0, 'Transcription request was superseded');

            return;
        }

        try {
            if (! $this->provider->available() || $this->provider->key() !== $transcription->providerKey) {
                throw new RuntimeException('Configured transcription provider is unavailable.');
            }
            $response = $transcription->providerJobId === null
                ? $this->provider->submit($this->source($transcription), $transcription->publicId)
                : $this->provider->poll($transcription->providerJobId);

            if ($response->complete) {
                $result = $response->result ?? throw new RuntimeException('The transcription provider returned no result.');
                $this->complete($transcription, $result);
                $this->reporter->succeeded($runPublicId, 'completed', 1, 1, 'Meeting transcription completed', ['completed' => 1]);

                return;
            }

            $externalJobId = $response->externalJobId ?? $transcription->providerJobId
                ?? throw new RuntimeException('The transcription provider returned no external job identifier.');
            if ($transcription->providerJobId === null) {
                $this->transcriptions->submitted($transcription->id, $externalJobId);
            } else {
                $this->transcriptions->processing($transcription->id);
            }
            $this->reporter->waiting($runPublicId, 'provider', 'Waiting for transcription provider', ['submitted' => 1]);
            $this->continue($runPublicId, max(1, config()->integer('transcription.poll_seconds', 15)));
        } catch (Throwable) {
            $attempt = $this->transcriptions->incrementAttempts($transcription->id);
            $maximum = max(1, config()->integer('transcription.max_attempts', 3));
            if ($attempt < $maximum) {
                $this->reporter->waiting($runPublicId, 'retry_backoff', 'Retrying transcription provider', ['attempts' => $attempt]);
                $base = max(1, config()->integer('transcription.retry_backoff_seconds', 30));
                $this->continue($runPublicId, $base * (2 ** ($attempt - 1)));

                return;
            }

            $this->fail($transcription);
            $this->reporter->failed($runPublicId, 'failed', 'Meeting transcription failed', 'The transcription provider could not complete the request.', ['attempts' => $attempt]);
        }
    }

    private function source(MeetingTranscription $transcription): TranscriptionSource
    {
        $recording = $this->recordings->findByInternalId($transcription->recordingId);
        if ($recording === null || ! $this->recordings->eligibleForTranscription($recording->id) || $recording->filePublicId === null) {
            throw new RuntimeException('Eligible retained Meeting recording is no longer available.');
        }
        $file = $this->files->cleanDownloadFile($recording->filePublicId, $transcription->requestedByUserId);

        return new TranscriptionSource(
            $recording->publicId,
            $file->publicId,
            $file->disk,
            $file->path,
            $file->filename,
            $file->mimeType,
            $file->sizeBytes,
        );
    }

    private function complete(MeetingTranscription $transcription, TranscriptionResult $result): void
    {
        $actor = $this->users->publicIdForInternalId($transcription->requestedByUserId);
        $this->transaction->run(function () use ($transcription, $result, $actor): void {
            $current = $this->transcriptions->find($transcription->publicId, true);
            if ($current === null || $current->status === TranscriptionStatus::Completed) {
                return;
            }
            $this->transcriptions->complete($current->id, $result);
            $this->search?->refresh('transcript', $current->publicId);
            $this->audit->record(new AuditEvent(
                module: 'chat', action: ChatAuditEvents::TRANSCRIPTION_COMPLETED, result: 'succeeded', source: 'queue',
                actorPublicId: $actor, targetType: 'meeting_transcription', targetPublicId: $current->publicId,
                aggregateType: 'meeting_transcription', aggregatePublicId: $current->publicId,
                metadata: ['provider' => $current->providerKey],
            ));
        });
    }

    private function fail(MeetingTranscription $transcription): void
    {
        $actor = $this->users->publicIdForInternalId($transcription->requestedByUserId);
        $this->transaction->run(function () use ($transcription, $actor): void {
            $current = $this->transcriptions->find($transcription->publicId, true);
            if ($current === null || $current->status === TranscriptionStatus::Completed) {
                return;
            }
            $this->transcriptions->fail($current->id, 'provider_failed');
            $this->audit->record(new AuditEvent(
                module: 'chat', action: ChatAuditEvents::TRANSCRIPTION_FAILED, result: 'failed', source: 'queue',
                actorPublicId: $actor, targetType: 'meeting_transcription', targetPublicId: $current->publicId,
                aggregateType: 'meeting_transcription', aggregatePublicId: $current->publicId,
                metadata: ['provider' => $current->providerKey, 'failure_code' => 'provider_failed'],
            ));
        });
    }

    private function continue(string $runPublicId, int $delaySeconds): void
    {
        ContinueTranscriptionJob::dispatch($runPublicId)->onQueue('managed-processes')->delay(now()->addSeconds($delaySeconds));
    }
}
