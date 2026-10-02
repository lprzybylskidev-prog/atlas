<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecordingStatus;

final readonly class MeetingRecording
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $occurrenceId,
        public int $initiatedByUserId,
        public MeetingRecordingStatus $status,
        public ?string $filePublicId,
        public ?string $startedAt,
        public ?string $endedAt,
        public ?int $durationSeconds,
        public ?string $retentionRemovedAt,
        public ?string $failureCode,
    ) {}
}
