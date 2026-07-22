<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Infrastructure\Meilisearch;

use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Exceptions\SearchUnavailable;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchClient;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchHit;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchQuery;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchResult;
use Meilisearch\Client;
use Throwable;

final readonly class MeilisearchSearchClient implements SearchClient
{
    public function __construct(
        private SearchIndexRegistry $indexes,
        private Client $client,
    ) {}

    public function search(SearchQuery $query): SearchResult
    {
        $descriptor = $this->indexes->get($query->indexKey);

        if ($descriptor === null) {
            throw new SearchUnavailable('Search index is not registered.');
        }

        try {
            $response = $this->client->index($descriptor->stableAlias)->search($query->term, [
                'filter' => $this->filters($query),
                'limit' => $query->limit,
                'offset' => $query->offset,
            ]);
        } catch (Throwable $exception) {
            throw new SearchUnavailable('Search projection is unavailable.', previous: $exception);
        }

        $hits = [];

        foreach ($response->getHits() as $hit) {
            $publicId = $hit['id'] ?? null;
            $moduleKey = $hit['module_key'] ?? null;

            if (! is_string($publicId) || ! is_string($moduleKey)) {
                continue;
            }

            unset($hit['id'], $hit['module_key'], $hit['team_public_ids'], $hit['permission_keys'], $hit['visibility_hash']);

            $hits[] = new SearchHit($publicId, $moduleKey, $this->stringKeyedFields($hit));
        }

        return new SearchResult(
            indexKey: $query->indexKey,
            hits: $hits,
            estimatedTotal: (int) ($response->getEstimatedTotalHits() ?? count($hits)),
        );
    }

    /**
     * @return list<string>
     */
    private function filters(SearchQuery $query): array
    {
        $filters = [
            sprintf('team_public_ids = "%s"', addcslashes($query->activeTeamPublicId, '"\\')),
            'permission_keys IN ['.$this->quotedList($query->permissionKeys).']',
        ];

        foreach ($query->filters as $field => $value) {
            if ($value === null) {
                continue;
            }

            $filters[] = sprintf('%s = "%s"', $field, addcslashes((string) $value, '"\\'));
        }

        return $filters;
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedList(array $values): string
    {
        return implode(', ', array_map(
            static fn (string $value): string => '"'.addcslashes($value, '"\\').'"',
            $values,
        ));
    }

    /**
     * @param  array<mixed, mixed>  $fields
     * @return array<string, mixed>
     */
    private function stringKeyedFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
