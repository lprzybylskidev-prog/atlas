<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminManagedProcessRunsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::MANAGED_PROCESS_RUNS;
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
        return ReportsPermissionCatalog::REQUEST;
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
        $rows = array_values(DB::table(DatabaseTable::MANAGED_PROCESS_RUNS.' as process_runs')
            ->leftJoin(DatabaseTable::USERS, 'process_runs.actor_user_id', '=', 'users.id')
            ->leftJoin(DatabaseTable::TEAMS, 'process_runs.team_id', '=', 'teams.id')
            ->leftJoin(DatabaseTable::IMPORT_EXECUTIONS, 'import_executions.process_run_id', '=', 'process_runs.id')
            ->leftJoin(DatabaseTable::FILE_OBJECTS, 'import_executions.file_object_id', '=', 'file_objects.id')
            ->leftJoin(DatabaseTable::MANAGED_PROCESS_RUN_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.process_run_id', '=', 'process_runs.id')
            ->leftJoin(DatabaseTable::USERS.' as acknowledged_users', 'acknowledged_users.id', '=', 'acknowledgements.acknowledged_by_user_id')
            ->orderByDesc('process_runs.created_at')
            ->limit(80)
            ->get([
                'process_runs.*',
                'users.email as actor_email',
                'teams.name as team_name',
                'acknowledgements.acknowledged_at',
                'acknowledged_users.email as acknowledged_by',
                'import_executions.import_key',
                'import_executions.source_type as import_source_type',
                'import_executions.idempotency_key',
                'import_executions.idempotency_state',
                'file_objects.original_name as import_file',
            ])
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
