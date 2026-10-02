<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Meetings\TranscriptionStatus;

final readonly class MeetingTranscription
{
    /** @param list<array{text:string,startsAtMilliseconds:?int,endsAtMilliseconds:?int,speaker:?string}>|null $segments */
    public function __construct(
        public int $id,
        public string $publicId,
        public int $recordingId,
        public int $requestedByUserId,
        public TranscriptionStatus $status,
        public string $providerKey,
        public ?string $providerJobId,
        public ?string $managedProcessRunPublicId,
        public int $attemptCount,
        public ?string $currentText,
        public ?array $segments,
        public ?string $failureCode,
        public ?string $completedAt,
    ) {}
}
