<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Presentation\Jobs;

use App\Modules\Optional\Search\Application\Indexing\SearchOutboxEventIndexer;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchEventProjector;
use App\Shared\Application\Outbox\IntegrationEventMessage;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class HandleSearchOutboxEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $schemaVersion,
        public string $sourceModule,
        public array $payload,
        public string $occurredAt,
        public string $correlationId,
        public ?string $causationId = null,
    ) {}

    public static function fromMessage(IntegrationEventMessage $event): self
    {
        return new self(
            eventId: $event->eventId,
            eventType: $event->eventType,
            schemaVersion: $event->schemaVersion,
            sourceModule: $event->sourceModule,
            payload: $event->payload,
            occurredAt: $event->occurredAt->format(DATE_ATOM),
            correlationId: $event->correlationId,
            causationId: $event->causationId,
        );
    }

    public function handle(SearchOutboxEventIndexer $indexer): void
    {
        $indexer->handle($this->message(), $this->projectors());
    }

    private function message(): IntegrationEventMessage
    {
        return new IntegrationEventMessage(
            eventId: $this->eventId,
            eventType: $this->eventType,
            schemaVersion: $this->schemaVersion,
            sourceModule: $this->sourceModule,
            payload: $this->payload,
            occurredAt: new DateTimeImmutable($this->occurredAt),
            correlationId: $this->correlationId,
            causationId: $this->causationId,
        );
    }

    /**
     * @return list<SearchEventProjector>
     */
    private function projectors(): array
    {
        $projectors = [];

        foreach (app()->tagged('atlas.search_event_projectors') as $projector) {
            if ($projector instanceof SearchEventProjector) {
                $projectors[] = $projector;
            }
        }

        return $projectors;
    }
}
