<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Infrastructure\Diagnostics;

use App\Modules\Core\Files\Infrastructure\Persistence\TableNames\FilesDatabaseTable;
use App\Shared\Application\Modules\Contracts\ModuleOperationalDiagnostics;
use Illuminate\Support\Facades\DB;

final class FileModuleOperationalDiagnostics implements ModuleOperationalDiagnostics
{
    public function moduleKey(): string
    {
        return 'files';
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    public function issues(): array
    {
        $rows = DB::table(FilesDatabaseTable::FILE_OBJECTS)
            ->selectRaw('scan_state, count(*) as total')
            ->whereNull('deleted_at')
            ->whereNull('acknowledged_at')
            ->groupBy('scan_state')
            ->pluck('total', 'scan_state');

        $blocked = $this->intValue($rows['infected'] ?? null) + $this->intValue($rows['failed'] ?? null) + $this->intValue($rows['unsupported'] ?? null);

        if ($blocked === 0) {
            return [];
        }

        return [[
            'severity' => 'degraded',
            'label' => 'Blocked files',
            'description' => 'File scan states are blocking file use and need review.',
            'value' => $blocked,
        ]];
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
