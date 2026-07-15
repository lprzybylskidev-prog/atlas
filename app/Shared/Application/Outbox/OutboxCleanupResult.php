<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox;

final readonly class OutboxCleanupResult
{
    public function __construct(
        public int $deletedPublished,
        public int $deletedFailed,
    ) {}
}
