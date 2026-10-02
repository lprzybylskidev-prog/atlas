<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Meetings\MeetingMode;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecurrence;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingStatus;
use DateTimeImmutable;

final readonly class MeetingRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $seriesPublicId,
        public int $organizerUserId,
        public string $conversationPublicId,
        public string $title,
        public ?string $description,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public MeetingMode $mode,
        public ?string $location,
        public ?MeetingRecurrence $recurrence,
        public MeetingStatus $status,
        public int $version,
    ) {}
}
