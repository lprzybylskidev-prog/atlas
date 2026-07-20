<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Query;

use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Permissions\SearchPermissionCatalog;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchClient;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchQuery;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchResult;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessRequest;
use RuntimeException;

final readonly class SearchService implements SearchClient
{
    public function __construct(
        private SearchIndexRegistry $indexes,
        private SearchClient $engine,
        private ModuleGate $moduleGate,
    ) {}

    public function search(SearchQuery $query): SearchResult
    {
        $descriptor = $this->indexes->get($query->indexKey);

        if ($descriptor === null) {
            throw new RuntimeException('Search index is not registered.');
        }

        foreach (['search', $descriptor->moduleKey] as $moduleKey) {
            if (! $this->moduleGate->allows(new ModuleAccessRequest(
                moduleKey: $moduleKey,
                activeTeamPublicId: $query->activeTeamPublicId,
                userPublicId: $query->userPublicId,
                requiredPermission: $moduleKey === 'search' ? SearchPermissionCatalog::QUERY : null,
            ))) {
                throw new RuntimeException('Search is not available for the current actor, team, module, or permission context.');
            }
        }

        return $this->engine->search($query);
    }
}
