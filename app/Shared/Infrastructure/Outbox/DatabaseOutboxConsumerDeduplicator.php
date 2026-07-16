<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\Outbox\Contracts\OutboxConsumerDeduplicator;
use App\Shared\Infrastructure\Database\DatabaseTable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseOutboxConsumerDeduplicator implements OutboxConsumerDeduplicator
{
    public function __construct(private ConnectionInterface $database) {}

    public function recordIfFirst(string $eventId, string $consumer): bool
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.uP');

        $inserted = $this->database->table(DatabaseTable::OUTBOX_CONSUMED_EVENTS)->insertOrIgnore([
            'event_id' => $eventId,
            'consumer' => $consumer,
            'consumed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted === 1;
    }
}
