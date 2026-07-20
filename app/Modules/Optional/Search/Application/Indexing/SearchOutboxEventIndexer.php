<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Indexing;

use App\Modules\Optional\Search\Application\Contracts\SearchDocumentStore;
use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchEventProjector;
use App\Shared\Application\Outbox\Contracts\OutboxConsumerDeduplicator;
use App\Shared\Application\Outbox\IntegrationEventMessage;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;

final readonly class SearchOutboxEventIndexer
{
    private const CONSUMER = 'search.indexing';

    public function __construct(
        private SearchIndexRegistry $indexes,
        private SearchDocumentStore $documents,
        private OutboxConsumerDeduplicator $deduplicator,
        private OperationalModuleGuard $modules,
    ) {}

    /**
     * @param  iterable<SearchEventProjector>  $projectors
     */
    public function handle(IntegrationEventMessage $event, iterable $projectors): bool
    {
        $this->modules->ensureAllowed('search');

        if (! $this->deduplicator->recordIfFirst($event->eventId, self::CONSUMER)) {
            return false;
        }

        foreach ($projectors as $projector) {
            if (! $projector->supports($event)) {
                continue;
            }

            foreach ($projector->documentsFor($event) as $document) {
                $descriptor = $this->indexes->get($document->indexKey);

                if ($descriptor === null) {
                    continue;
                }

                $this->modules->ensureAllowed($descriptor->moduleKey);
                $this->documents->configure($descriptor);
                $this->documents->upsert($descriptor, $document);
            }

            foreach ($projector->deletedDocumentIdsFor($event) as $indexKey => $documentPublicIds) {
                $descriptor = $this->indexes->get($indexKey);

                if ($descriptor === null || $documentPublicIds === []) {
                    continue;
                }

                $this->modules->ensureAllowed($descriptor->moduleKey);
                $this->documents->delete($descriptor, $documentPublicIds);
            }
        }

        return true;
    }
}
