<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminImportExecutionsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::IMPORT_EXECUTIONS;
    }

    public function tableName(): string
    {
        return 'Import executions';
    }

    public function owningModuleKey(): string
    {
        return 'imports';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-import-executions-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'runPublicId' => 'Run public ID',
            'importKey' => 'Import',
            'sourceType' => 'Source',
            'status' => 'Status',
            'idempotencyKey' => 'Idempotency key',
            'idempotencyState' => 'Idempotency state',
            'statistics' => 'Statistics',
            'createdAt' => 'Created',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_values(DB::table(DatabaseTable::IMPORT_EXECUTIONS)
            ->join(DatabaseTable::MANAGED_PROCESS_RUNS, 'import_executions.process_run_id', '=', 'process_runs.id')
            ->orderByDesc('import_executions.created_at')
            ->limit(80)
            ->get([
                'import_executions.public_id',
                'import_executions.import_key',
                'import_executions.source_type',
                'import_executions.statistics',
                'import_executions.idempotency_key',
                'import_executions.idempotency_state',
                'import_executions.created_at',
                'process_runs.public_id as run_public_id',
                'process_runs.status',
            ])
            ->map(static fn (object $row): array => [
                'publicId' => self::stringValue($row->public_id ?? null),
                'runPublicId' => self::stringValue($row->run_public_id ?? null),
                'importKey' => self::stringValue($row->import_key ?? null),
                'sourceType' => self::stringValue($row->source_type ?? null),
                'status' => self::stringValue($row->status ?? null),
                'statistics' => self::compactJson($row->statistics ?? null),
                'idempotencyKey' => self::stringValue($row->idempotency_key ?? null),
                'idempotencyState' => self::stringValue($row->idempotency_state ?? null),
                'createdAt' => self::stringValue($row->created_at ?? null),
            ])
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
            foreach (['importKey' => 'import', 'status' => 'status', 'sourceType' => 'source'] as $column => $filter) {
                $value = self::filterValue($request, $filter);

                if ($value !== '' && $value !== 'all' && $row[$column] !== $value) {
                    return false;
                }
            }

            return self::dateRangeMatches(self::stringValue($row['createdAt'] ?? ''), self::filterValue($request, 'from'), self::filterValue($request, 'to'));
        }));
    }

    private static function compactJson(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '{}';
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return '{}';
        }

        return json_encode($decoded, JSON_THROW_ON_ERROR);
    }
}
