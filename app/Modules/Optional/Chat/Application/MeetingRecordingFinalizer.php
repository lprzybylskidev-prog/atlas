<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingAssembler;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingStore;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecordingStatus;
use DateTimeImmutable;
use Throwable;

final readonly class MeetingRecordingFinalizer
{
    public function __construct(
        private MeetingRecordingStore $recordings,
        private MeetingRecordingAssembler $assembler,
        private FileStorage $files,
        private FileLifecycle $fileLifecycle,
        private ChatTransaction $transaction,
    ) {}

    /** @return array{ready:int,failed:int,pending:int} */
    public function finalizePending(int $limit = 10): array
    {
        $result = ['ready' => 0, 'failed' => 0, 'pending' => 0];
        foreach ($this->recordings->awaitingFinalization($limit) as $recording) {
            $segments = $this->recordings->segments($recording->id);
            $paths = array_map(static fn ($segment): string => $segment->stagingPath, $segments);
            if (! $this->assembler->segmentsReady($paths)) {
                $result['pending']++;

                continue;
            }
            $finalPath = '';
            $filePublicId = null;
            try {
                $finalPath = $this->assembler->assemble($paths, $recording->publicId);
                $file = $this->files->storeGeneratedFromPath(
                    'meeting-recording-'.$recording->publicId.'.mp4',
                    'video/mp4',
                    $finalPath,
                    $recording->initiatedByUserId,
                    metadata: ['owner_module' => 'chat', 'artifact_type' => 'meeting_recording', 'recording_public_id' => $recording->publicId],
                );
                $filePublicId = $file->publicId;
                $duration = $recording->startedAt === null || $recording->endedAt === null
                    ? 0
                    : max(0, (new DateTimeImmutable($recording->endedAt))->getTimestamp() - (new DateTimeImmutable($recording->startedAt))->getTimestamp());
                $this->transaction->run(fn () => $this->recordings->markReady($recording->id, $file->publicId, $duration));
                $this->assembler->cleanup($paths, $finalPath);
                $result['ready']++;
            } catch (Throwable) {
                if ($filePublicId !== null) {
                    $this->fileLifecycle->delete($filePublicId, reason: 'Meeting recording finalization rollback');
                }
                $this->transaction->run(fn () => $this->recordings->setStatus($recording->id, MeetingRecordingStatus::Failed, 'finalization_failed'));
                $result['failed']++;
            }
        }

        return $result;
    }
}
