<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Exports;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Persistence\TableNames\ManagedProcessesDatabaseTable;
use App\Shared\Application\Exports\AbstractAdminDataTableExportProvider;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\Imports\Contracts\ImportAdminVisibility;
use App\Shared\Application\Tables\RegisteredTables;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class AdminManagedProcessRunsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(
        private UserLookup $users,
        private TeamLookup $teams,
        private ImportAdminVisibility $imports,
    ) {}

    public function tableKey(): string
    {
        return RegisteredTables::MANAGED_PROCESS_RUNS;
    }

    public function tableName(): string
    {
        return 'Managed process runs';
    }

    public function owningModuleKey(): string
    {
        return 'managed_processes';
    }

    public function requestPermission(): string
    {
        return ExportPermissions::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-managed-process-runs-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'processKey' => 'Process',
            'status' => 'Status',
            'sourceType' => 'Source',
            'moduleKey' => 'Module',
            'importKey' => 'Import',
            'importSourceType' => 'Import source',
            'importFile' => 'Import file',
            'idempotencyKey' => 'Idempotency key',
            'idempotencyState' => 'Idempotency state',
            'handlingStatus' => 'Handling status',
            'acknowledgedAt' => 'Handled at',
            'acknowledgedBy' => 'Handled by',
            'progressLabel' => 'Progress',
            'progressCurrent' => 'Done',
            'progressTotal' => 'Total',
            'actor' => 'Actor',
            'team' => 'Team',
            'startedAt' => 'Started',
            'finishedAt' => 'Finished',
            'createdAt' => 'Created',
            'queueName' => 'Queue',
            'correlationId' => 'Correlation ID',
            'safeErrorSummary' => 'Safe error summary',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $records = DB::table(ManagedProcessesDatabaseTable::RUNS.' as process_runs')
            ->leftJoin(ManagedProcessesDatabaseTable::RUN_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.process_run_id', '=', 'process_runs.id')
            ->orderByDesc('process_runs.created_at')
            ->limit(80)
            ->get([
                'process_runs.*',
                'acknowledgements.acknowledged_at',
                'acknowledgements.acknowledged_by_user_id',
            ]);
        $rows = array_values(collect($this->enrichRunRecords($records->all()))
            ->map(static function (object $run): array {
                $status = self::stringValue($run->status ?? null);
                $acknowledgedAt = self::stringValue($run->acknowledged_at ?? null);

                return [
                    'publicId' => self::stringValue($run->public_id ?? null),
                    'processKey' => self::stringValue($run->process_key ?? null),
                    'moduleKey' => self::stringValue($run->module_key ?? null),
                    'importKey' => self::stringValue($run->import_key ?? null),
                    'importSourceType' => self::stringValue($run->import_source_type ?? null),
                    'importFile' => self::stringValue($run->import_file ?? null),
                    'idempotencyKey' => self::stringValue($run->idempotency_key ?? null),
                    'idempotencyState' => self::stringValue($run->idempotency_state ?? null),
                    'status' => $status,
                    'handlingStatus' => $acknowledgedAt !== '' ? 'handled' : (in_array($status, ['failed', 'succeeded_with_warnings', 'cancelled', 'expired'], true) ? 'needs_attention' : 'ok'),
                    'acknowledgedAt' => $acknowledgedAt,
                    'acknowledgedBy' => self::stringValue($run->acknowledged_by ?? null),
                    'sourceType' => self::stringValue($run->source_type ?? null),
                    'progressCurrent' => is_numeric($run->progress_current ?? null) ? (int) $run->progress_current : 0,
                    'progressTotal' => is_numeric($run->progress_total ?? null) ? (int) $run->progress_total : null,
                    'progressLabel' => self::stringValue($run->progress_label ?? null),
                    'safeErrorSummary' => self::stringValue($run->safe_error_summary ?? null),
                    'queueName' => self::stringValue($run->queue_name ?? null),
                    'correlationId' => self::stringValue($run->correlation_id ?? null),
                    'actor' => self::stringValue($run->actor_email ?? null),
                    'team' => self::stringValue($run->team_name ?? null),
                    'createdAt' => self::stringValue($run->created_at ?? null),
                    'startedAt' => self::stringValue($run->started_at ?? null),
                    'finishedAt' => self::stringValue($run->finished_at ?? null),
                ];
            })
            ->all());

        foreach ($this->sorted($this->filtered($this->filteredByControls($rows, $request), $request), $request) as $row) {
            yield $row;
        }
    }

    /**
     * @param  array<int, stdClass>  $records
     * @return list<stdClass>
     */
    private function enrichRunRecords(array $records): array
    {
        $records = array_values($records);
        $actorIds = [];
        $acknowledgedByIds = [];
        $teamIds = [];
        $runIds = [];

        foreach ($records as $record) {
            $actorId = self::nullableInt($record->actor_user_id ?? null);
            $acknowledgedById = self::nullableInt($record->acknowledged_by_user_id ?? null);
            $teamId = self::nullableInt($record->team_id ?? null);
            $runId = self::nullableInt($record->id ?? null);

            if ($actorId !== null) {
                $actorIds[] = $actorId;
            }

            if ($acknowledgedById !== null) {
                $acknowledgedByIds[] = $acknowledgedById;
            }

            if ($teamId !== null) {
                $teamIds[] = $teamId;
            }

            if ($runId !== null) {
                $runIds[] = $runId;
            }
        }

        $userSummaries = $this->users->displaySummariesForInternalIds(array_values(array_unique(array_merge($actorIds, $acknowledgedByIds))));
        $teamSummaries = $this->teams->summariesForInternalIds(array_values(array_unique($teamIds)));
        $importSummaries = $this->imports->summariesForProcessRunIds(array_values(array_unique($runIds)));

        foreach ($records as $record) {
            $actorId = self::nullableInt($record->actor_user_id ?? null);
            $acknowledgedById = self::nullableInt($record->acknowledged_by_user_id ?? null);
            $teamId = self::nullableInt($record->team_id ?? null);
            $runId = self::nullableInt($record->id ?? null);
            $actor = $actorId === null ? null : ($userSummaries[$actorId] ?? null);
            $acknowledgedBy = $acknowledgedById === null ? null : ($userSummaries[$acknowledgedById] ?? null);
            $team = $teamId === null ? null : ($teamSummaries[$teamId] ?? null);
            $import = $runId === null ? null : ($importSummaries[$runId] ?? null);

            $record->actor_email = $actor?->email;
            $record->team_name = $team?->name;
            $record->acknowledged_by = $acknowledgedBy?->email;
            $record->import_key = $import?->importKey;
            $record->import_source_type = $import?->sourceType;
            $record->import_file = $import?->fileOriginalName;
            $record->idempotency_key = $import?->idempotencyKey;
            $record->idempotency_state = $import?->idempotencyState;
        }

        return $records;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  list<array<string, scalar|\Stringable|null>>  $rows
     * @return list<array<string, scalar|\Stringable|null>>
     */
    private function filteredByControls(array $rows, ReportExportGenerationRequest $request): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($request): bool {
            foreach (['processKey' => 'process', 'status' => 'status', 'sourceType' => 'source', 'moduleKey' => 'module', 'importKey' => 'import', 'idempotencyState' => 'idempotency'] as $column => $filter) {
                $value = self::filterValue($request, $filter);

                if ($value !== '' && $value !== 'all' && $row[$column] !== $value) {
                    return false;
                }
            }

            return self::dateRangeMatches(self::stringValue($row['startedAt'] ?? $row['createdAt'] ?? ''), self::filterValue($request, 'from'), self::filterValue($request, 'to'));
        }));
    }
}
