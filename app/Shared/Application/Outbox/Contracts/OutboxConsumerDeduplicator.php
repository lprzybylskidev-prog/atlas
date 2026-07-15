<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox\Contracts;

interface OutboxConsumerDeduplicator
{
    public function recordIfFirst(string $eventId, string $consumer): bool;
}
