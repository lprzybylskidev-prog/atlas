<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Broadcasting;

use App\Modules\Optional\Chat\Application\Contracts\ChatRealtimePublisher;
use Illuminate\Support\Facades\Broadcast;

final class LaravelChatRealtimePublisher implements ChatRealtimePublisher
{
    public function conversation(string $conversationPublicId, string $event, array $payload): void
    {
        Broadcast::presence('chat.conversation.'.$conversationPublicId)
            ->as($event)
            ->with($payload)
            ->sendNow();
    }

    public function user(string $userPublicId, string $event, array $payload): void
    {
        Broadcast::private('chat.user.'.$userPublicId)
            ->as($event)
            ->with($payload)
            ->sendNow();
    }
}
