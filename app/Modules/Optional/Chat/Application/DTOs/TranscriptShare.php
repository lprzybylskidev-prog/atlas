<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class TranscriptShare
{
    public function __construct(
        public string $publicId,
        public int $recipientUserId,
        public int $sharedByUserId,
    ) {}
}
