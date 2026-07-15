<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox;

final readonly class OutboxLagMetrics
{
    public function __construct(
        public int $pending,
        public int $publishing,
        public int $failed,
        public ?int $oldestPendingAgeSeconds,
    ) {}
}
