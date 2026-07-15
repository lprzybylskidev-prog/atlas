<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox;

final readonly class OutboxRelayResult
{
    public function __construct(
        public int $claimed,
        public int $published,
        public int $scheduledForRetry = 0,
        public int $failed = 0,
    ) {}
}
