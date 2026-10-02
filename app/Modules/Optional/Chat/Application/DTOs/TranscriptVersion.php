<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class TranscriptVersion
{
    public function __construct(
        public string $publicId,
        public int $version,
        public string $source,
        public ?int $createdByUserId,
        public string $text,
        public string $createdAt,
    ) {}
}
