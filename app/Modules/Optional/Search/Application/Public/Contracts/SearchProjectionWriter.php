<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\Contracts;

use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;

interface SearchProjectionWriter
{
    public function upsert(SearchDocument $document): void;

    /** @param list<string> $documentPublicIds */
    public function delete(string $indexKey, array $documentPublicIds): void;
}
