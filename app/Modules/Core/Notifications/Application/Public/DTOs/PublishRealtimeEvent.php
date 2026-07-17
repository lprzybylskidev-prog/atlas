<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\DTOs;

final readonly class PublishRealtimeEvent
{
    /**
     * @param  array<string, scalar|null>  $payload
     */
    public function __construct(
        public string $topic,
        public string $eventType,
        public ?string $userPublicId = null,
        public ?string $teamPublicId = null,
        public array $payload = [],
    ) {}
}
