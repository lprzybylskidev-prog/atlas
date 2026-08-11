<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\Contracts;

use App\Modules\Core\Files\Application\Public\DTOs\FileDisplaySummary;

interface FileLookup
{
    /**
     * @param  list<int>  $fileIds
     * @return array<int, FileDisplaySummary>
     */
    public function displaySummariesForInternalIds(array $fileIds): array;
}
