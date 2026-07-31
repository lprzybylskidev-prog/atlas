<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\Search\Application\SearchRebuildProcess;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminSearchRebuildsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::SEARCH_REBUILDS;
    }

    public function tableName(): string
    {
        return 'Search rebuild runs';
    }

    public function owningModuleKey(): string
    {
        return 'search';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-search-rebuilds-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'status' => 'Status',
            'currentStage' => 'Stage',
            'progressLabel' => 'Progress',
            'progressCurrent' => 'Done',
            'progressTotal' => 'Total',
            'createdAt' => 'Created',
            'startedAt' => 'Started',
            'finishedAt' => 'Finished',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_values(DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)
            ->where('process_key', SearchRebuildProcess::KEY)
            ->orderByDesc('created_at')
            ->get(['public_id', 'status', 'current_stage', 'progress_current', 'progress_total', 'progress_label', 'created_at', 'started_at', 'finished_at'])
            ->map(static fn (object $row): array => [
                'publicId' => self::stringValue($row->public_id ?? null),
                'status' => self::stringValue($row->status ?? null),
                'currentStage' => self::stringValue($row->current_stage ?? null),
                'progressCurrent' => is_numeric($row->progress_current ?? null) ? (int) $row->progress_current : 0,
                'progressTotal' => is_numeric($row->progress_total ?? null) ? (int) $row->progress_total : null,
                'progressLabel' => self::stringValue($row->progress_label ?? null),
                'createdAt' => self::stringValue($row->created_at ?? null),
                'startedAt' => self::stringValue($row->started_at ?? null),
                'finishedAt' => self::stringValue($row->finished_at ?? null),
            ])
            ->all());

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
            yield $row;
        }
    }
}
