<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime;

use App\Modules\Optional\ManagedProcesses\Application\DTOs\ProcessLogEntry;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessLogSeverity;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessReporter;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;

final readonly class ManagedProcessRunnerReporter implements ManagedProcessReporter
{
    public function __construct(private ManagedProcessRunner $runner) {}

    public function info(string $runPublicId, string $eventType, string $message, ?string $stage = null, ?array $safeContext = null): void
    {
        $this->runner->log($runPublicId, new ProcessLogEntry(ProcessLogSeverity::Info, $eventType, $message, $stage, $safeContext));
    }

    public function running(string $runPublicId, ?string $stage = null, ?int $current = null, ?int $total = null, ?string $label = null, ?array $counters = null): void
    {
        $this->runner->updateProgress($runPublicId, ProcessRunStatus::Running, $stage, $current, $total, $label, $counters);
    }

    public function succeeded(string $runPublicId, ?string $stage = null, ?int $current = null, ?int $total = null, ?string $label = null, ?array $counters = null, ?array $resultSummary = null): void
    {
        $this->runner->updateProgress($runPublicId, ProcessRunStatus::Succeeded, $stage, $current, $total, $label, $counters, $resultSummary);
    }
}
