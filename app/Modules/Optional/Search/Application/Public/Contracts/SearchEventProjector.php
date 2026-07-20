<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\Contracts;

use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;
use App\Shared\Application\Outbox\IntegrationEventMessage;

interface SearchEventProjector
{
    public function supports(IntegrationEventMessage $event): bool;

    /**
     * @return list<SearchDocument>
     */
    public function documentsFor(IntegrationEventMessage $event): array;

    /**
     * @return array<string, list<string>>
     */
    public function deletedDocumentIdsFor(IntegrationEventMessage $event): array;
}
