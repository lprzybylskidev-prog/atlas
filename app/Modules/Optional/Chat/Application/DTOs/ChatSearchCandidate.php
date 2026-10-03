<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class ChatSearchCandidate
{
    public function __construct(
        public string $type,
        public string $publicId,
        public string $title,
        public string $snippet,
        public ?string $conversationPublicId = null,
        public ?string $messagePublicId = null,
        public ?string $transcriptionPublicId = null,
        public ?string $occurredAt = null,
        public ?string $authorName = null,
        public bool $transcriptParticipant = false,
    ) {}

    /** @return array<string, scalar|null> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'publicId' => $this->publicId,
            'title' => $this->title,
            'snippet' => $this->snippet,
            'conversationPublicId' => $this->conversationPublicId,
            'messagePublicId' => $this->messagePublicId,
            'transcriptionPublicId' => $this->transcriptionPublicId,
            'occurredAt' => $this->occurredAt,
            'authorName' => $this->authorName,
        ];
    }
}
