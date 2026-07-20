<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Public\Contracts;

use App\Modules\Optional\Integrations\Application\Public\DTOs\ExternalIdMapping;

interface ExternalIdMappingStore
{
    public function map(ExternalIdMapping $mapping, ?int $actorId = null): void;

    public function findInternalPublicId(string $integrationKey, string $sourceSystem, string $entityType, string $externalId, ?int $teamId = null): ?string;
}
