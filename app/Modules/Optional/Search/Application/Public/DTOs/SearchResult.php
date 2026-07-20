<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\DTOs;

readonly class SearchResult
{
    /**
     * @param  list<SearchHit>  $hits
     */
    public function __construct(
        public string $indexKey,
        public array $hits,
        public int $estimatedTotal,
        public bool $degraded = false,
    ) {}
}
