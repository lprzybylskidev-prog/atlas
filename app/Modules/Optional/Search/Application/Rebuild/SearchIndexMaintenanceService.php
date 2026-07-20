<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Rebuild;

use App\Modules\Optional\Search\Application\Contracts\SearchDocumentStore;
use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchRebuildDocumentProvider;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexMaintenanceReport;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final readonly class SearchIndexMaintenanceService
{
    public function __construct(
        private SearchIndexRegistry $indexes,
        private SearchDocumentStore $documents,
    ) {}

    /**
     * @param  iterable<SearchRebuildDocumentProvider>  $providers
     * @return list<SearchIndexMaintenanceReport>
     */
    public function rebuild(?string $moduleKey, ?string $indexKey, iterable $providers): array
    {
        $reports = [];
        $providerMap = $this->providerMap($providers);

        foreach ($this->selectedIndexes($moduleKey, $indexKey) as $descriptor) {
            $provider = $providerMap[$descriptor->key] ?? null;
            $expected = $provider?->expectedDocumentCount() ?? 0;
            $physicalIndex = $this->physicalIndexName($descriptor);

            $this->documents->createPhysicalIndex($descriptor, $physicalIndex);

            $written = 0;

            if ($provider !== null) {
                foreach ($provider->documents() as $document) {
                    $this->documents->upsertInto($descriptor, $physicalIndex, $document);
                    $written++;
                }
            }

            $indexed = $this->documents->count($physicalIndex);
            $discrepancy = abs($expected - $indexed);

            if ($discrepancy !== 0) {
                throw new RuntimeException(sprintf('Search rebuild validation failed for [%s]; expected %d document(s), indexed %d.', $descriptor->key, $expected, $indexed));
            }

            $this->documents->promote($descriptor, $physicalIndex);

            $reports[] = new SearchIndexMaintenanceReport(
                indexKey: $descriptor->key,
                stableAlias: $descriptor->stableAlias,
                physicalIndex: $physicalIndex,
                expectedDocuments: $expected,
                indexedDocuments: $indexed,
                discrepancy: $discrepancy,
                lagSeconds: 0,
            );
        }

        return $reports;
    }

    /**
     * @param  iterable<SearchRebuildDocumentProvider>  $providers
     * @return list<SearchIndexMaintenanceReport>
     */
    public function compare(?string $moduleKey, ?string $indexKey, iterable $providers): array
    {
        $reports = [];
        $providerMap = $this->providerMap($providers);

        foreach ($this->selectedIndexes($moduleKey, $indexKey) as $descriptor) {
            $expected = ($providerMap[$descriptor->key] ?? null)?->expectedDocumentCount() ?? 0;
            $indexed = $this->documents->count($descriptor->stableAlias);

            $reports[] = new SearchIndexMaintenanceReport(
                indexKey: $descriptor->key,
                stableAlias: $descriptor->stableAlias,
                physicalIndex: $descriptor->stableAlias,
                expectedDocuments: $expected,
                indexedDocuments: $indexed,
                discrepancy: abs($expected - $indexed),
                lagSeconds: 0,
            );
        }

        return $reports;
    }

    /**
     * @return list<SearchIndexDescriptor>
     */
    private function selectedIndexes(?string $moduleKey, ?string $indexKey): array
    {
        return array_values(array_filter(
            $this->indexes->all(),
            static fn (SearchIndexDescriptor $descriptor): bool => ($moduleKey === null || $descriptor->moduleKey === $moduleKey)
                && ($indexKey === null || $descriptor->key === $indexKey),
        ));
    }

    /**
     * @param  iterable<SearchRebuildDocumentProvider>  $providers
     * @return array<string, SearchRebuildDocumentProvider>
     */
    private function providerMap(iterable $providers): array
    {
        $map = [];

        foreach ($providers as $provider) {
            $map[$provider->indexKey()] = $provider;
        }

        return $map;
    }

    private function physicalIndexName(SearchIndexDescriptor $descriptor): string
    {
        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('YmdHis');

        return sprintf('%s_rebuild_%s', $descriptor->stableAlias, $timestamp);
    }
}
