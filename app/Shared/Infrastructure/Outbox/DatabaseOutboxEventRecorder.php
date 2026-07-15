<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\Outbox\Contracts\OutboxEventRecorder;
use App\Shared\Application\Outbox\IntegrationEventMessage;
use App\Shared\Application\Outbox\OutboxEventStatus;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseOutboxEventRecorder implements OutboxEventRecorder
{
    public function __construct(private ConnectionInterface $database) {}

    public function record(IntegrationEventMessage $event): void
    {
        $now = $this->formatTimestamp(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        $this->database->table('outbox_events')->insert([
            'event_id' => $event->eventId,
            'event_type' => $event->eventType,
            'schema_version' => $event->schemaVersion,
            'source_module' => $event->sourceModule,
            'payload' => json_encode($event->payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $this->formatTimestamp($event->occurredAt),
            'correlation_id' => $event->correlationId,
            'causation_id' => $event->causationId,
            'status' => OutboxEventStatus::Pending->value,
            'attempts' => 0,
            'next_attempt_at' => null,
            'published_at' => null,
            'failed_at' => null,
            'failure_details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function formatTimestamp(DateTimeInterface $dateTime): string
    {
        return DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s.uP');
    }
}
