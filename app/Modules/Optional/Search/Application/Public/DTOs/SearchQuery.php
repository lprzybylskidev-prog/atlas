<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\DTOs;

use InvalidArgumentException;

readonly class SearchQuery
{
    /**
     * @param  list<string>  $permissionKeys
     * @param  array<string, scalar|null>  $filters
     */
    public function __construct(
        public string $indexKey,
        public string $term,
        public string $activeTeamPublicId,
        public string $userPublicId,
        public array $permissionKeys,
        public int $limit = 20,
        public int $offset = 0,
        public array $filters = [],
    ) {
        foreach ([$indexKey, $term, $activeTeamPublicId, $userPublicId] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Search query identifiers, term, active team, and user must be non-empty strings.');
            }
        }

        if ($permissionKeys === []) {
            throw new InvalidArgumentException('Search queries must include the caller permission scope.');
        }

        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Search query limit must be between 1 and 100.');
        }

        if ($offset < 0) {
            throw new InvalidArgumentException('Search query offset must not be negative.');
        }
    }
}
