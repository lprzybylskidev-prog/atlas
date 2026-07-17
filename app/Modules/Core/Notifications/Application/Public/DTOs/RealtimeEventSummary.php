<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\DTOs;

final readonly class RealtimeEventSummary
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $publicId,
        public string $topic,
        public string $eventType,
        public ?string $teamPublicId,
        public array $payload,
        public string $createdAt,
    ) {}
}
