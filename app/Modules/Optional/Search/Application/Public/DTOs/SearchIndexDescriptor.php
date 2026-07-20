<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\DTOs;

use InvalidArgumentException;

readonly class SearchIndexDescriptor
{
    /**
     * @param  list<string>  $searchableFields
     * @param  list<string>  $filterableFields
     * @param  list<string>  $sortableFields
     */
    public function __construct(
        public string $key,
        public string $moduleKey,
        public string $stableAlias,
        public array $searchableFields,
        public array $filterableFields,
        public array $sortableFields,
        public bool $containsSensitiveData = false,
        public bool $supportsDeletion = true,
        public bool $supportsAnonymization = true,
    ) {
        foreach ([$key, $moduleKey, $stableAlias] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Search index descriptor identifiers must be non-empty strings.');
            }
        }

        if ($searchableFields === []) {
            throw new InvalidArgumentException('Search indexes must declare at least one searchable field.');
        }

        foreach (['module_key', 'team_public_ids', 'permission_keys'] as $requiredFilter) {
            if (! in_array($requiredFilter, $filterableFields, true)) {
                throw new InvalidArgumentException(sprintf('Search indexes must declare [%s] as a filterable field.', $requiredFilter));
            }
        }
    }
}
