<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Indexing;

use App\Modules\Optional\Search\Application\Contracts\SearchDocumentStore;
use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchProjectionWriter;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;

final readonly class SearchProjectionWriteService implements SearchProjectionWriter
{
    public function __construct(
        private SearchIndexRegistry $indexes,
        private SearchDocumentStore $documents,
        private OperationalModuleGuard $modules,
    ) {}

    public function upsert(SearchDocument $document): void
    {
        $descriptor = $this->indexes->get($document->indexKey);
        if ($descriptor === null) {
            return;
        }
        $this->modules->ensureAllowed('search');
        $this->modules->ensureAllowed($descriptor->moduleKey);
        $this->documents->configure($descriptor);
        $this->documents->upsert($descriptor, $document);
    }

    public function delete(string $indexKey, array $documentPublicIds): void
    {
        $descriptor = $this->indexes->get($indexKey);
        if ($descriptor === null || $documentPublicIds === []) {
            return;
        }
        $this->modules->ensureAllowed('search');
        $this->modules->ensureAllowed($descriptor->moduleKey);
        $this->documents->delete($descriptor, $documentPublicIds);
    }
}
