<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use DateTimeImmutable;

final readonly class MessageDraft
{
    public function __construct(
        public string $publicId,
        public string $body,
        public ?string $replyToMessagePublicId,
        public int $version,
        public DateTimeImmutable $updatedAt,
    ) {}
}
