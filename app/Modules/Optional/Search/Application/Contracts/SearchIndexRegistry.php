<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Contracts;

use App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor;

interface SearchIndexRegistry
{
    /**
     * @return list<SearchIndexDescriptor>
     */
    public function all(): array;

    public function get(string $indexKey): ?SearchIndexDescriptor;
}
