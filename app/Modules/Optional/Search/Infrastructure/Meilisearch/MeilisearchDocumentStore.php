<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Infrastructure\Meilisearch;

use App\Modules\Optional\Search\Application\Contracts\SearchDocumentStore;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor;
use InvalidArgumentException;
use Meilisearch\Client;

final readonly class MeilisearchDocumentStore implements SearchDocumentStore
{
    public function __construct(private Client $client) {}

    public function configure(SearchIndexDescriptor $descriptor): void
    {
        $this->configureIndex($descriptor, $descriptor->stableAlias);
    }

    public function createPhysicalIndex(SearchIndexDescriptor $descriptor, string $physicalIndex): void
    {
        $this->client->createIndex($physicalIndex, ['primaryKey' => 'id']);
        $this->configureIndex($descriptor, $physicalIndex);
    }

    public function upsert(SearchIndexDescriptor $descriptor, SearchDocument $document): void
    {
        $this->upsertInto($descriptor, $descriptor->stableAlias, $document);
    }

    public function upsertInto(SearchIndexDescriptor $descriptor, string $physicalIndex, SearchDocument $document): void
    {
        $this->client
            ->index($physicalIndex)
            ->addDocuments([$document->toMeilisearchPayload()], 'id');
    }

    public function delete(SearchIndexDescriptor $descriptor, array $documentPublicIds): void
    {
        if ($documentPublicIds === []) {
            return;
        }

        $this->client
            ->index($descriptor->stableAlias)
            ->deleteDocuments($documentPublicIds);
    }

    public function count(string $indexName): int
    {
        $stats = $this->client->index($indexName)->stats();
        $documents = $stats['numberOfDocuments'] ?? 0;

        return is_numeric($documents) ? (int) $documents : 0;
    }

    public function promote(SearchIndexDescriptor $descriptor, string $physicalIndex): void
    {
        $this->client->swapIndexes([['indexes' => [$descriptor->stableAlias, $physicalIndex]]]);
    }

    private function configureIndex(SearchIndexDescriptor $descriptor, string $indexName): void
    {
        $index = $this->client->index($indexName);
        $index->updateSearchableAttributes($this->nonEmptyStrings($descriptor->searchableFields));
        $index->updateFilterableAttributes($this->nonEmptyStrings($descriptor->filterableFields));
        $index->updateSortableAttributes($this->nonEmptyStrings($descriptor->sortableFields));
    }

    /**
     * @param  list<string>  $values
     * @return list<non-empty-string>
     */
    private function nonEmptyStrings(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $field = trim($value);

            if ($field === '') {
                throw new InvalidArgumentException('Search index fields must be non-empty strings.');
            }

            $normalized[] = $field;
        }

        return $normalized;
    }
}
