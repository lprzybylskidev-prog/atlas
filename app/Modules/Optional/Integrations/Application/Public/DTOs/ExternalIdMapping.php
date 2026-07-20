<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Public\DTOs;

use InvalidArgumentException;

final readonly class ExternalIdMapping
{
    public function __construct(
        public string $integrationKey,
        public string $sourceSystem,
        public string $entityType,
        public string $externalId,
        public string $internalPublicId,
        public ?int $teamId = null,
    ) {
        foreach ([$integrationKey, $sourceSystem, $entityType, $externalId, $internalPublicId] as $field) {
            if (trim($field) === '') {
                throw new InvalidArgumentException('External ID mapping fields must be non-empty strings.');
            }
        }
    }
}
