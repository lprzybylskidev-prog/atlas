<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Public\Contracts;

interface ManagedProcessReporter
{
    /**
     * @param  array<string, scalar|null>|null  $safeContext
     */
    public function info(string $runPublicId, string $eventType, string $message, ?string $stage = null, ?array $safeContext = null): void;

    /**
     * @param  array<string, int>|null  $counters
     */
    public function running(string $runPublicId, ?string $stage = null, ?int $current = null, ?int $total = null, ?string $label = null, ?array $counters = null): void;

    /**
     * @param  array<string, int>|null  $counters
     * @param  array<string, scalar|null>|null  $resultSummary
     */
    public function succeeded(string $runPublicId, ?string $stage = null, ?int $current = null, ?int $total = null, ?string $label = null, ?array $counters = null, ?array $resultSummary = null): void;
}
