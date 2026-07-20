<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\Contracts;

use App\Modules\Optional\Search\Application\Public\DTOs\SearchQuery;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchResult;

interface SearchClient
{
    public function search(SearchQuery $query): SearchResult;
}
