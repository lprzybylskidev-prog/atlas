<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Contracts;

use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor;

interface SearchDocumentStore
{
    public function configure(SearchIndexDescriptor $descriptor): void;

    public function createPhysicalIndex(SearchIndexDescriptor $descriptor, string $physicalIndex): void;

    public function upsert(SearchIndexDescriptor $descriptor, SearchDocument $document): void;

    public function upsertInto(SearchIndexDescriptor $descriptor, string $physicalIndex, SearchDocument $document): void;

    /**
     * @param  list<string>  $documentPublicIds
     */
    public function delete(SearchIndexDescriptor $descriptor, array $documentPublicIds): void;

    public function count(string $indexName): int;

    public function promote(SearchIndexDescriptor $descriptor, string $physicalIndex): void;
}
