<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

interface ChatRealtimePublisher
{
    /** @param array<string, mixed> $payload */
    public function conversation(string $conversationPublicId, string $event, array $payload): void;

    /** @param array<string, mixed> $payload */
    public function user(string $userPublicId, string $event, array $payload): void;
}
