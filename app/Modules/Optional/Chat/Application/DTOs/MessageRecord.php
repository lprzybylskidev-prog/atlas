<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use DateTimeImmutable;

final readonly class MessageRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $conversationId,
        public int $authorUserId,
        public string $body,
        public ?int $replyToMessageId,
        public ?int $forwardedFromMessageId,
        public string $clientMessageKey,
        public string $requestHash,
        public int $version,
        public ?DateTimeImmutable $editedAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
