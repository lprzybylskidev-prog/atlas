<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\MeetingRecording;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecordingSegment;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecordingShare;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecordingSegmentStatus;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecordingStatus;
use DateTimeImmutable;

interface MeetingRecordingStore
{
    public function forOccurrence(int $occurrenceId, bool $forUpdate = false): ?MeetingRecording;

    public function find(string $publicId, bool $forUpdate = false): ?MeetingRecording;

    public function findByInternalId(int $id): ?MeetingRecording;

    public function create(int $occurrenceId, int $initiatedByUserId): MeetingRecording;

    public function nextSegmentSequence(int $recordingId): int;

    public function setStatus(int $recordingId, MeetingRecordingStatus $status, ?string $failureCode = null): void;

    public function createSegment(int $recordingId, string $egressId, string $stagingPath): MeetingRecordingSegment;

    public function activeSegment(int $recordingId, bool $forUpdate = false): ?MeetingRecordingSegment;

    public function setSegmentStatus(int $segmentId, MeetingRecordingSegmentStatus $status): void;

    /** @return list<MeetingRecording> */
    public function awaitingFinalization(int $limit): array;

    /** @return list<MeetingRecordingSegment> */
    public function segments(int $recordingId): array;

    public function markReady(int $recordingId, string $filePublicId, int $durationSeconds): void;

    public function participantHasAccess(int $recordingId, int $userId): bool;

    public function eligibleForTranscription(int $recordingId): bool;

    public function sharedRecipientHasAccess(int $recordingId, int $userId): bool;

    public function share(int $recordingId, int $recipientUserId, int $sharedByUserId): MeetingRecordingShare;

    public function revokeShare(string $sharePublicId, int $sharedByUserId): bool;

    /** @return list<MeetingRecordingShare> */
    public function shares(int $recordingId): array;

    /** @return list<MeetingRecording> */
    public function expiredReady(DateTimeImmutable $cutoff, int $limit): array;

    public function markRemovedByRetention(int $recordingId): void;

    /** @return array{ready:int,bytes:int,oldestEndedAt:?string} */
    public function retentionSummary(?DateTimeImmutable $cutoff): array;

    public function recordingRetentionDays(): ?int;

    public function hasRecordingRetentionSetting(): bool;

    public function setRecordingRetentionDays(?int $days): void;
}
