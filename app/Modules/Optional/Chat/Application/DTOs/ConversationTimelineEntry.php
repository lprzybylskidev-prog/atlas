<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Conversations\TimelineEntryType;

final readonly class ConversationTimelineEntry
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        public string $publicId,
        public TimelineEntryType $type,
        public ?int $actorUserId,
        public ?int $subjectUserId,
        public array $metadata,
        public string $occurredAt,
    ) {}
}
