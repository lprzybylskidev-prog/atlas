<?php

declare(strict_types=1);

namespace App\Shared\Application\ManagedProcesses\Contracts;

use App\Shared\Application\ManagedProcesses\DTOs\ManagedProcessRunSummary;

interface ManagedProcessRunInspector
{
    public function internalIdForPublicId(string $runPublicId): ?int;

    /**
     * @return list<ManagedProcessRunSummary>
     */
    public function recentRunsForProcess(string $processKey, int $limit): array;

    /**
     * @return array<string, mixed>
     */
    public function inputSnapshot(string $runPublicId): array;
}
