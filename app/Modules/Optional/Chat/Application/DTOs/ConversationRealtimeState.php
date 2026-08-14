<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class ConversationRealtimeState
{
    public function __construct(
        public ?string $lastDeliveredMessagePublicId,
        public ?string $lastReadMessagePublicId,
        public ?string $firstUnreadMessagePublicId,
        public int $unreadCount,
    ) {}
}
