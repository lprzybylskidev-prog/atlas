<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Infrastructure\Meilisearch;

use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchClient;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchHit;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchQuery;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchResult;
use App\Modules\Optional\Search\Application\Public\Exceptions\SearchUnavailable;
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
                'filter' => $this->filters($query, $descriptor->filterableFields),
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
     * @param  list<string>  $filterableFields
     * @return list<string>
     */
    private function filters(SearchQuery $query, array $filterableFields): array
    {
        $teamFilter = sprintf('team_public_ids = "%s"', addcslashes($query->activeTeamPublicId, '"\\'));
        if (in_array('global_scope', $filterableFields, true)) {
            $teamFilter = sprintf('(%s OR global_scope = true)', $teamFilter);
        }

        $filters = [
            $teamFilter,
            'permission_keys IN ['.$this->quotedList($query->permissionKeys).']',
        ];

        foreach ($query->filters as $field => $value) {
            if ($value === null) {
                continue;
            }

            [$baseField, $operator] = $this->filterField($field);
            if (! in_array($baseField, $filterableFields, true)) {
                continue;
            }

            $filters[] = sprintf('%s %s "%s"', $baseField, $operator, addcslashes((string) $value, '"\\'));
        }

        return $filters;
    }

    /** @return array{string,string} */
    private function filterField(string $field): array
    {
        $operator = '=';
        foreach (['__gte' => '>=', '__lte' => '<='] as $suffix => $candidate) {
            if (str_ends_with($field, $suffix)) {
                $field = substr($field, 0, -strlen($suffix));
                $operator = $candidate;
                break;
            }
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $field) !== 1) {
            throw new SearchUnavailable('Search filter is invalid.');
        }

        return [$field, $operator];
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
