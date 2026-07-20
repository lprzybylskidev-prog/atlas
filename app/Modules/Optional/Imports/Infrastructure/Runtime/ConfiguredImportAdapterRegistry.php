<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Infrastructure\Runtime;

use App\Modules\Optional\Imports\Application\Contracts\ImportAdapterRegistry;
use App\Modules\Optional\Imports\Application\Contracts\ImportSourceAdapter;

final class ConfiguredImportAdapterRegistry implements ImportAdapterRegistry
{
    /**
     * @return list<ImportSourceAdapter>
     */
    public function all(): array
    {
        return [];
    }

    public function get(string $sourceType): ?ImportSourceAdapter
    {
        return null;
    }
}
