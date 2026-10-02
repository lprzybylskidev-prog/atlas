<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class MeetingRecordingShare
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $recordingId,
        public int $recipientUserId,
        public int $sharedByUserId,
        public ?string $revokedAt,
    ) {}
}
