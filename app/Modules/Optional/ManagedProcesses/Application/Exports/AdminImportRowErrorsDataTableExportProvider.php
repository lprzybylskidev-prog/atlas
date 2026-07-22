<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminImportRowErrorsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::IMPORT_ROW_ERRORS;
    }

    public function tableName(): string
    {
        return 'Import row errors';
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
        return 'admin-import-row-errors-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'runPublicId' => 'Run public ID',
            'importPublicId' => 'Import public ID',
            'rowNumber' => 'Row',
            'fieldName' => 'Field',
            'severity' => 'Severity',
            'errorCode' => 'Code',
            'message' => 'Message',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $runPublicId = self::filterValue($request, 'run');

        if ($runPublicId === '') {
            return;
        }

        $rows = DB::table(DatabaseTable::IMPORT_ROW_ERRORS)
            ->join(DatabaseTable::IMPORT_EXECUTIONS, 'import_row_errors.import_execution_id', '=', 'import_executions.id')
            ->join(DatabaseTable::MANAGED_PROCESS_RUNS, 'import_executions.process_run_id', '=', 'process_runs.id')
            ->where('process_runs.public_id', $runPublicId)
            ->orderBy('import_row_errors.row_number')
            ->get([
                'import_row_errors.public_id',
                'import_row_errors.row_number',
                'import_row_errors.field_name',
                'import_row_errors.severity',
                'import_row_errors.error_code',
                'import_row_errors.message',
                'import_executions.public_id as import_public_id',
                'process_runs.public_id as run_public_id',
            ])
            ->map(static fn (object $error): array => [
                'publicId' => self::stringValue($error->public_id ?? ''),
                'runPublicId' => self::stringValue($error->run_public_id ?? ''),
                'importPublicId' => self::stringValue($error->import_public_id ?? ''),
                'rowNumber' => is_numeric($error->row_number ?? null) ? (int) $error->row_number : null,
                'fieldName' => self::stringValue($error->field_name ?? ''),
                'severity' => self::stringValue($error->severity ?? ''),
                'errorCode' => self::stringValue($error->error_code ?? ''),
                'message' => self::stringValue($error->message ?? ''),
            ])
            ->values()
            ->all();

        foreach ($this->sorted($this->filtered(array_values($rows), $request), $request) as $row) {
            yield $row;
        }
    }
}
