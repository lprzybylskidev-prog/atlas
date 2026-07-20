<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Infrastructure\Runtime;

use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor;

final class ConfiguredSearchIndexRegistry implements SearchIndexRegistry
{
    /** @var array<string, SearchIndexDescriptor>|null */
    private ?array $indexes = null;

    public function all(): array
    {
        return array_values($this->indexes());
    }

    public function get(string $indexKey): ?SearchIndexDescriptor
    {
        return $this->indexes()[$indexKey] ?? null;
    }

    /**
     * @return array<string, SearchIndexDescriptor>
     */
    private function indexes(): array
    {
        if ($this->indexes !== null) {
            return $this->indexes;
        }

        $indexes = [];

        foreach (app()->tagged('atlas.search_index_descriptors') as $descriptor) {
            if ($descriptor instanceof SearchIndexDescriptor) {
                $indexes[$descriptor->key] = $descriptor;
            }
        }

        $this->indexes = $indexes;

        return $indexes;
    }
}
