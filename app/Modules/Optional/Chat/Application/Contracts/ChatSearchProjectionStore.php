<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\ChatSearchCandidate;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;

interface ChatSearchProjectionStore
{
    /** @return iterable<SearchDocument> */
    public function documents(): iterable;

    public function expectedDocumentCount(): int;

    /** @return list<SearchDocument> */
    public function documentsForSource(string $type, string $publicId): array;

    /** @return list<string> */
    public function deletedDocumentIdsForSource(string $type, string $publicId): array;

    public function resolve(string $documentId, int $viewerUserId): ?ChatSearchCandidate;
}
