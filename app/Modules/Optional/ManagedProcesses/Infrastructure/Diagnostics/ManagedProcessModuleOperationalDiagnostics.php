<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Infrastructure\Diagnostics;

use App\Modules\Optional\ManagedProcesses\Infrastructure\Persistence\TableNames\ManagedProcessesDatabaseTable;
use App\Shared\Application\Modules\Contracts\ModuleOperationalDiagnostics;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ManagedProcessModuleOperationalDiagnostics implements ModuleOperationalDiagnostics
{
    public function moduleKey(): string
    {
        return 'managed_processes';
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    public function issues(): array
    {
        $active = (int) DB::table(ManagedProcessesDatabaseTable::RUNS)->whereIn('status', ['draft', 'queued', 'running', 'waiting'])->count();
        $failed = (int) $this->unacknowledgedRunsQuery()->where('process_runs.status', 'failed')->where('process_runs.created_at', '>=', now()->subDay())->count();
        $warnings = (int) $this->unacknowledgedRunsQuery()->where('process_runs.status', 'succeeded_with_warnings')->where('process_runs.created_at', '>=', now()->subDay())->count();
        $issues = [];

        if ($failed > 0) {
            $issues[] = ['severity' => 'unhealthy', 'label' => 'Failed process runs', 'description' => 'Managed process runs failed during the last 24 hours.', 'value' => $failed];
        }

        if ($warnings > 0) {
            $issues[] = ['severity' => 'degraded', 'label' => 'Process warnings', 'description' => 'Managed process runs completed with warnings during the last 24 hours.', 'value' => $warnings];
        }

        if ($active > 0) {
            $issues[] = ['severity' => 'info', 'label' => 'Active process runs', 'description' => 'Managed process runs are currently active or queued.', 'value' => $active];
        }

        return $issues;
    }

    private function unacknowledgedRunsQuery(): Builder
    {
        return DB::table(ManagedProcessesDatabaseTable::RUNS.' as process_runs')
            ->leftJoin(ManagedProcessesDatabaseTable::RUN_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.process_run_id', '=', 'process_runs.id')
            ->whereNull('acknowledgements.process_run_id');
    }
}
