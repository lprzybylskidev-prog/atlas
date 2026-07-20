<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Application\Contracts;

interface ImportAdapterRegistry
{
    /**
     * @return list<ImportSourceAdapter>
     */
    public function all(): array;

    public function get(string $sourceType): ?ImportSourceAdapter;
}
