<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class MessageReaction
{
    public function __construct(
        public string $emoji,
        public string $userPublicId,
    ) {}
}
