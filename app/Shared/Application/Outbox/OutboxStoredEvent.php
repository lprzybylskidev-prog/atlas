<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox;

use DateTimeImmutable;

/**
 * @phpstan-type JsonPayload array<string, mixed>
 */
final readonly class OutboxStoredEvent
{
    /**
     * @param  JsonPayload  $payload
     */
    public function __construct(
        public int $id,
        public string $eventId,
        public string $eventType,
        public int $schemaVersion,
        public string $sourceModule,
        public array $payload,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
        public ?string $causationId,
        public int $attempts,
    ) {}
}
