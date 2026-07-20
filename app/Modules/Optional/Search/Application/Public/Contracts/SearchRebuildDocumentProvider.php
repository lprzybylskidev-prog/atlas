<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\Contracts;

use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;

interface SearchRebuildDocumentProvider
{
    public function indexKey(): string;

    public function expectedDocumentCount(): int;

    /**
     * @return iterable<SearchDocument>
     */
    public function documents(): iterable;
}
