<?php

declare(strict_types=1);

namespace App\Shared\Application\ManagedProcesses\Contracts;

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

    /** @param array<string, int>|null $counters */
    public function waiting(string $runPublicId, ?string $stage = null, ?string $label = null, ?array $counters = null): void;

    /** @param array<string, int>|null $counters */
    public function failed(string $runPublicId, string $stage, string $label, string $safeErrorSummary, ?array $counters = null): void;

    /**
     * @param  array<string, int>|null  $counters
     * @param  array<string, scalar|null>|null  $resultSummary
     */
    public function succeeded(string $runPublicId, ?string $stage = null, ?int $current = null, ?int $total = null, ?string $label = null, ?array $counters = null, ?array $resultSummary = null): void;
}
