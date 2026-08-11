<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Exports;

use App\Modules\Optional\Search\Application\SearchRebuildProcess;
use App\Shared\Application\Exports\AbstractAdminDataTableExportProvider;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunInspector;
use App\Shared\Application\ManagedProcesses\DTOs\ManagedProcessRunSummary;
use App\Shared\Application\Tables\RegisteredTables;

final readonly class AdminSearchRebuildsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private ManagedProcessRunInspector $runs) {}

    public function tableKey(): string
    {
        return RegisteredTables::SEARCH_REBUILDS;
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
        return ExportPermissions::REQUEST;
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
        $rows = array_map(
            static fn (ManagedProcessRunSummary $run): array => [
                'publicId' => $run->publicId,
                'status' => $run->status,
                'currentStage' => $run->currentStage,
                'progressCurrent' => $run->progressCurrent,
                'progressTotal' => $run->progressTotal,
                'progressLabel' => $run->progressLabel,
                'createdAt' => $run->createdAt,
                'startedAt' => $run->startedAt,
                'finishedAt' => $run->finishedAt,
            ],
            $this->runs->recentRunsForProcess(SearchRebuildProcess::KEY, 80),
        );

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
            yield $row;
        }
    }
}
