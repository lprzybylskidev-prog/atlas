<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Exports;

use App\Shared\Application\Exports\AbstractAdminDataTableExportProvider;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\Imports\Contracts\ImportAdminVisibility;
use App\Shared\Application\Imports\DTOs\ImportRowErrorSummary;
use App\Shared\Application\Tables\RegisteredTables;

final readonly class AdminImportRowErrorsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private ImportAdminVisibility $imports) {}

    public function tableKey(): string
    {
        return RegisteredTables::IMPORT_ROW_ERRORS;
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
        return ExportPermissions::REQUEST;
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

        $rows = array_map(static fn (ImportRowErrorSummary $error): array => [
            'publicId' => $error->publicId,
            'runPublicId' => $error->runPublicId,
            'importPublicId' => $error->importPublicId,
            'rowNumber' => $error->rowNumber,
            'fieldName' => self::stringValue($error->fieldName),
            'severity' => $error->severity,
            'errorCode' => $error->errorCode,
            'message' => $error->message,
        ], $this->imports->rowErrorsForProcessRunPublicId($runPublicId));

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
            yield $row;
        }
    }
}
