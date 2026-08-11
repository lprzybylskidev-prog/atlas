<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Infrastructure\Diagnostics;

use App\Modules\Optional\Imports\Infrastructure\Persistence\TableNames\ImportsDatabaseTable;
use App\Shared\Application\Modules\Contracts\ModuleOperationalDiagnostics;
use Illuminate\Support\Facades\DB;

final class ImportModuleOperationalDiagnostics implements ModuleOperationalDiagnostics
{
    public function moduleKey(): string
    {
        return 'imports';
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    public function issues(): array
    {
        $rowWarnings = (int) DB::table(ImportsDatabaseTable::ROW_ERRORS)->where('severity', 'warning')->count();
        $rowErrors = (int) DB::table(ImportsDatabaseTable::ROW_ERRORS)->where('severity', 'error')->count();

        if ($rowErrors > 0) {
            return [['severity' => 'degraded', 'label' => 'Import row errors', 'description' => 'Import row errors are available for operator review.', 'value' => $rowErrors]];
        }

        if ($rowWarnings > 0) {
            return [['severity' => 'info', 'label' => 'Import row warnings', 'description' => 'Import row warnings are available for operator review.', 'value' => $rowWarnings]];
        }

        return [];
    }
}
