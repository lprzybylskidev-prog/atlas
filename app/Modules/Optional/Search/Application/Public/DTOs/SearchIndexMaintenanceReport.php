<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\DTOs;

readonly class SearchIndexMaintenanceReport
{
    public function __construct(
        public string $indexKey,
        public string $stableAlias,
        public string $physicalIndex,
        public int $expectedDocuments,
        public int $indexedDocuments,
        public int $discrepancy,
        public int $lagSeconds,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toSummary(): array
    {
        return [
            'index_key' => $this->indexKey,
            'stable_alias' => $this->stableAlias,
            'physical_index' => $this->physicalIndex,
            'expected_documents' => $this->expectedDocuments,
            'indexed_documents' => $this->indexedDocuments,
            'discrepancy' => $this->discrepancy,
            'lag_seconds' => $this->lagSeconds,
        ];
    }
}
