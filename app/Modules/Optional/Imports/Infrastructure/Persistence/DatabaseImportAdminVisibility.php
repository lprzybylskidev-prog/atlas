<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Infrastructure\Persistence;

use App\Modules\Core\Files\Application\Public\Contracts\FileLookup;
use App\Modules\Core\Files\Application\Public\DTOs\FileDisplaySummary;
use App\Modules\Optional\Imports\Infrastructure\Persistence\TableNames\ImportsDatabaseTable;
use App\Shared\Application\Imports\Contracts\ImportAdminVisibility;
use App\Shared\Application\Imports\DTOs\ImportExecutionDetail;
use App\Shared\Application\Imports\DTOs\ImportExecutionRunSummary;
use App\Shared\Application\Imports\DTOs\ImportRowErrorSummary;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunInspector;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseImportAdminVisibility implements ImportAdminVisibility
{
    public function __construct(
        private FileLookup $files,
        private ManagedProcessRunInspector $processRuns,
    ) {}

    public function summariesForProcessRunIds(array $processRunIds): array
    {
        if ($processRunIds === []) {
            return [];
        }

        $executions = DB::table(ImportsDatabaseTable::EXECUTIONS)
            ->whereIn('process_run_id', array_values(array_unique($processRunIds)))
            ->get([
                'process_run_id',
                'public_id',
                'import_key',
                'source_type',
                'file_object_id',
                'idempotency_key',
                'idempotency_state',
            ]);
        $fileSummaries = $this->fileSummaries($executions->pluck('file_object_id')->all());
        $summaries = [];

        foreach ($executions as $execution) {
            $processRunId = $this->int($execution->process_run_id ?? null);

            if ($processRunId === null) {
                continue;
            }

            $fileId = $this->int($execution->file_object_id ?? null);
            $fileSummary = $fileId === null ? null : ($fileSummaries[$fileId] ?? null);
            $summaries[$processRunId] = new ImportExecutionRunSummary(
                processRunId: $processRunId,
                publicId: $this->stringValue($execution->public_id ?? null),
                importKey: $this->stringValue($execution->import_key ?? null),
                sourceType: $this->stringValue($execution->source_type ?? null),
                idempotencyKey: $this->string($execution->idempotency_key ?? null),
                idempotencyState: $this->stringValue($execution->idempotency_state ?? null),
                fileOriginalName: $fileSummary?->originalName,
            );
        }

        ksort($summaries);

        return $summaries;
    }

    public function executionForProcessRunId(int $processRunId): ?ImportExecutionDetail
    {
        $execution = DB::table(ImportsDatabaseTable::EXECUTIONS)->where('process_run_id', $processRunId)->first();

        if (! is_object($execution)) {
            return null;
        }

        return new ImportExecutionDetail(
            publicId: $this->stringValue($execution->public_id ?? null),
            importKey: $this->stringValue($execution->import_key ?? null),
            sourceType: $this->stringValue($execution->source_type ?? null),
            apiReference: $this->string($execution->api_reference ?? null),
            externalReference: $this->string($execution->external_reference ?? null),
            mappingSnapshot: $this->decode($execution->mapping_snapshot ?? null),
            sourceMetadata: $this->decode($execution->source_metadata ?? null),
            statistics: $this->decode($execution->statistics ?? null),
            idempotencyKey: $this->string($execution->idempotency_key ?? null),
            idempotencyState: $this->stringValue($execution->idempotency_state ?? null),
            errors: $this->rowErrorsForExecution(
                importExecutionId: $this->int($execution->id ?? null) ?? 0,
                importPublicId: $this->stringValue($execution->public_id ?? null),
                runPublicId: '',
                includeSafeContext: true,
            ),
        );
    }

    public function rowErrorsForProcessRunPublicId(string $processRunPublicId): array
    {
        $processRunId = $processRunPublicId === '' ? null : $this->processRuns->internalIdForPublicId($processRunPublicId);

        if ($processRunId === null) {
            return [];
        }

        return array_values(DB::table(ImportsDatabaseTable::ROW_ERRORS)
            ->join(ImportsDatabaseTable::EXECUTIONS, 'import_row_errors.import_execution_id', '=', 'import_executions.id')
            ->where('import_executions.process_run_id', $processRunId)
            ->orderBy('import_row_errors.row_number')
            ->get([
                'import_row_errors.public_id',
                'import_row_errors.row_number',
                'import_row_errors.field_name',
                'import_row_errors.severity',
                'import_row_errors.error_code',
                'import_row_errors.message',
                'import_row_errors.safe_context',
                'import_executions.public_id as import_public_id',
            ])
            ->map(function (object $error) use ($processRunPublicId): ImportRowErrorSummary {
                $error->run_public_id = $processRunPublicId;

                return $this->rowError($error, true);
            })
            ->values()
            ->all());
    }

    public function executionCount(): int
    {
        return (int) DB::table(ImportsDatabaseTable::EXECUTIONS)->count();
    }

    public function distinctExecutionValues(string $column): array
    {
        if (! in_array($column, ['import_key', 'idempotency_state'], true)) {
            return [];
        }

        return array_values(DB::table(ImportsDatabaseTable::EXECUTIONS)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter(static fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all());
    }

    /**
     * @param  array<mixed>  $fileIds
     * @return array<int, FileDisplaySummary>
     */
    private function fileSummaries(array $fileIds): array
    {
        $ids = [];

        foreach ($fileIds as $fileId) {
            $id = $this->int($fileId);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $this->files->displaySummariesForInternalIds($ids);
    }

    /**
     * @return list<ImportRowErrorSummary>
     */
    private function rowErrorsForExecution(int $importExecutionId, string $importPublicId, string $runPublicId, bool $includeSafeContext): array
    {
        if ($importExecutionId <= 0) {
            return [];
        }

        return array_values(DB::table(ImportsDatabaseTable::ROW_ERRORS)
            ->where('import_execution_id', $importExecutionId)
            ->orderBy('row_number')
            ->get([
                'public_id',
                'row_number',
                'field_name',
                'severity',
                'error_code',
                'message',
                'safe_context',
            ])
            ->map(function (object $error) use ($importPublicId, $runPublicId, $includeSafeContext): ImportRowErrorSummary {
                $error->import_public_id = $importPublicId;
                $error->run_public_id = $runPublicId;

                return $this->rowError($error, $includeSafeContext);
            })
            ->values()
            ->all());
    }

    private function rowError(object $error, bool $includeSafeContext): ImportRowErrorSummary
    {
        return new ImportRowErrorSummary(
            publicId: $this->stringValue($error->public_id ?? null),
            runPublicId: $this->stringValue($error->run_public_id ?? null),
            importPublicId: $this->stringValue($error->import_public_id ?? null),
            rowNumber: $this->int($error->row_number ?? null),
            fieldName: $this->string($error->field_name ?? null),
            severity: $this->stringValue($error->severity ?? null),
            errorCode: $this->stringValue($error->error_code ?? null),
            message: $this->stringValue($error->message ?? null),
            safeContext: $includeSafeContext ? $this->decode($error->safe_context ?? null) : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        $values = [];

        foreach ($decoded as $key => $item) {
            if (! is_string($key)) {
                return [];
            }

            $values[$key] = $item;
        }

        return $values;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = (string) $value;

        return $string === '' ? null : $string;
    }

    private function stringValue(mixed $value): string
    {
        return $this->string($value) ?? '';
    }
}
