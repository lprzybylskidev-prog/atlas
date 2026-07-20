<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\DTOs;

use InvalidArgumentException;

readonly class SearchDocument
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $teamPublicIds
     * @param  list<string>  $permissionKeys
     */
    public function __construct(
        public string $publicId,
        public string $indexKey,
        public string $moduleKey,
        public array $fields,
        public array $teamPublicIds = [],
        public array $permissionKeys = [],
        public ?string $visibilityHash = null,
    ) {
        foreach ([$publicId, $indexKey, $moduleKey] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Search document identifiers must be non-empty strings.');
            }
        }

        $reservedFields = ['id', 'module_key', 'team_public_ids', 'permission_keys', 'visibility_hash'];

        foreach ($reservedFields as $field) {
            if (array_key_exists($field, $fields)) {
                throw new InvalidArgumentException(sprintf('Search document field [%s] is reserved.', $field));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toMeilisearchPayload(): array
    {
        return [
            'id' => $this->publicId,
            'module_key' => $this->moduleKey,
            'team_public_ids' => $this->teamPublicIds,
            'permission_keys' => $this->permissionKeys,
            'visibility_hash' => $this->visibilityHash,
            ...$this->fields,
        ];
    }
}
