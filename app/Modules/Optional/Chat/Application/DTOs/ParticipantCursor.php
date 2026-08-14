<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class ParticipantCursor
{
    public function __construct(
        public string $userPublicId,
        public ?string $lastDeliveredMessagePublicId,
        public ?string $lastReadMessagePublicId,
    ) {}
}
