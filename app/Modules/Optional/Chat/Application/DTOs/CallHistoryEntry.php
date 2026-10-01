<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class CallHistoryEntry
{
    public function __construct(
        public string $publicId,
        public string $conversationLabel,
        public string $conversationType,
        public string $direction,
        public string $initialMode,
        public string $state,
        public string $startedAt,
        public ?string $answeredAt,
        public ?string $endedAt,
        public int $durationSeconds,
        public bool $canRejoin,
    ) {}
}
