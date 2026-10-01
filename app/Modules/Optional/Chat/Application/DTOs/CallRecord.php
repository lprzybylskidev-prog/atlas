<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Calls\CallStatus;

final readonly class CallRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $conversationId,
        public int $startedByUserId,
        public string $requestHash,
        public string $roomName,
        public bool $initialCameraEnabled,
        public CallStatus $status,
        public string $startedAt,
        public ?string $answeredAt,
        public ?string $endedAt,
    ) {}
}
