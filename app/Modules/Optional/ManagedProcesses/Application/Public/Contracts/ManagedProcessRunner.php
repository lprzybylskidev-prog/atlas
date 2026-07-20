<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Public\Contracts;

use App\Modules\Optional\ManagedProcesses\Application\DTOs\ProcessLogEntry;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;

interface ManagedProcessRunner
{
    /**
     * @param  array<string, mixed>|null  $input
     */
    public function start(
        string $processKey,
        string $sourceType,
        ?array $input,
        ?string $actorPublicId,
        ?string $teamPublicId,
        ?string $causationId = null,
    ): string;

    public function retry(string $runPublicId, ?string $actorPublicId, ?string $teamPublicId, string $reason): string;

    public function cancel(string $runPublicId, ?string $actorPublicId, ?string $teamPublicId, string $reason): void;

    public function log(string $runPublicId, ProcessLogEntry $entry): void;

    /**
     * @param  array<string, int>|null  $counters
     * @param  array<string, scalar|null>|null  $resultSummary
     */
    public function updateProgress(
        string $runPublicId,
        ProcessRunStatus $status,
        ?string $stage = null,
        ?int $current = null,
        ?int $total = null,
        ?string $label = null,
        ?array $counters = null,
        ?array $resultSummary = null,
        ?string $safeErrorSummary = null,
    ): void;
}
