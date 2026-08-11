<?php

declare(strict_types=1);

namespace App\Shared\Application\Imports\Contracts;

use App\Shared\Application\Imports\DTOs\ImportExecutionDetail;
use App\Shared\Application\Imports\DTOs\ImportExecutionRunSummary;
use App\Shared\Application\Imports\DTOs\ImportRowErrorSummary;

interface ImportAdminVisibility
{
    /**
     * @param  list<int>  $processRunIds
     * @return array<int, ImportExecutionRunSummary>
     */
    public function summariesForProcessRunIds(array $processRunIds): array;

    public function executionForProcessRunId(int $processRunId): ?ImportExecutionDetail;

    /**
     * @return list<ImportRowErrorSummary>
     */
    public function rowErrorsForProcessRunPublicId(string $processRunPublicId): array;

    public function executionCount(): int;

    /**
     * @return list<string>
     */
    public function distinctExecutionValues(string $column): array;
}
