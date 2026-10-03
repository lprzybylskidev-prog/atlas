<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Search;

use App\Modules\Optional\Chat\Application\ChatSearch;
use App\Modules\Optional\Chat\Application\Contracts\ChatSearchProjectionStore;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchRebuildDocumentProvider;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;

final readonly class ChatSearchProjection implements SearchRebuildDocumentProvider
{
    public function __construct(private ChatSearchProjectionStore $store) {}

    public function indexKey(): string
    {
        return ChatSearch::INDEX_KEY;
    }

    public function expectedDocumentCount(): int
    {
        return $this->store->expectedDocumentCount();
    }

    /** @return iterable<SearchDocument> */
    public function documents(): iterable
    {
        return $this->store->documents();
    }
}
