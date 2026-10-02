<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecordingSegmentStatus;

final readonly class MeetingRecordingSegment
{
    public function __construct(
        public int $id,
        public int $recordingId,
        public int $sequence,
        public string $egressId,
        public string $stagingPath,
        public MeetingRecordingSegmentStatus $status,
        public ?string $startedAt,
        public ?string $endedAt,
    ) {}
}
