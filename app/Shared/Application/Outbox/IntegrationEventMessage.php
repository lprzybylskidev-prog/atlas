<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * @phpstan-type JsonPayload array<string, mixed>
 */
final readonly class IntegrationEventMessage
{
    /**
     * @param  JsonPayload  $payload
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $schemaVersion,
        public string $sourceModule,
        public array $payload,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
        public ?string $causationId = null,
    ) {
        $this->guardNonEmpty($eventId, 'eventId');
        $this->guardNonEmpty($eventType, 'eventType');
        $this->guardNonEmpty($sourceModule, 'sourceModule');
        $this->guardNonEmpty($correlationId, 'correlationId');

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Integration event schema version must be greater than zero.');
        }

        if ($causationId !== null) {
            $this->guardNonEmpty($causationId, 'causationId');
        }
    }

    private function guardNonEmpty(string $value, string $name): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Integration event %s must be a non-empty string.', $name));
        }
    }
}
