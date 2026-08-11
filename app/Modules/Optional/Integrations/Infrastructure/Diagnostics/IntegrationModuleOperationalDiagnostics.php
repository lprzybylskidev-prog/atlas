<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Infrastructure\Diagnostics;

use App\Modules\Optional\Integrations\Infrastructure\Persistence\TableNames\IntegrationsDatabaseTable;
use App\Shared\Application\Modules\Contracts\ModuleOperationalDiagnostics;
use Illuminate\Support\Facades\DB;

final class IntegrationModuleOperationalDiagnostics implements ModuleOperationalDiagnostics
{
    public function moduleKey(): string
    {
        return 'integrations';
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    public function issues(): array
    {
        $openCircuits = (int) DB::table(IntegrationsDatabaseTable::CIRCUIT_BREAKERS)->where('state', 'open')->count();
        $failedRuns = (int) DB::table(IntegrationsDatabaseTable::SYNC_RUNS)->where('status', 'failed')->where('started_at', '>=', now()->subDay())->count();
        $issues = [];

        if ($openCircuits > 0) {
            $issues[] = [
                'severity' => 'unhealthy',
                'label' => 'Open circuits',
                'description' => 'Integration circuit breakers are open.',
                'value' => $openCircuits,
            ];
        }

        if ($failedRuns > 0) {
            $issues[] = [
                'severity' => 'degraded',
                'label' => 'Failed sync runs',
                'description' => 'Integration synchronization runs failed during the last 24 hours.',
                'value' => $failedRuns,
            ];
        }

        return $issues;
    }
}
