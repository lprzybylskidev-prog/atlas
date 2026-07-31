<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Queues\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Queues\FailedJobAdminRows;

final readonly class AdminFailedJobsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private FailedJobAdminRows $rows) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::FAILED_JOBS;
    }

    public function tableName(): string
    {
        return 'Failed jobs';
    }

    public function owningModuleKey(): string
    {
        return 'authorization';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-failed-jobs-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'uuid' => 'UUID',
            'connection' => 'Connection',
            'queue' => 'Queue',
            'failedAt' => 'Failed at',
            'displayName' => 'Job',
            'jobClass' => 'Job class',
            'exceptionType' => 'Exception type',
            'exceptionMessage' => 'Exception message',
            'handlingStatus' => 'Handling status',
            'acknowledgedAt' => 'Handled at',
            'acknowledgedBy' => 'Handled by',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_map(static fn (array $row): array => [
            'uuid' => $row['uuid'],
            'connection' => $row['connection'],
            'queue' => $row['queue'],
            'failedAt' => $row['failedAt'],
            'displayName' => $row['displayName'],
            'jobClass' => $row['jobClass'],
            'exceptionType' => $row['exceptionType'],
            'exceptionMessage' => $row['exceptionMessage'],
            'handlingStatus' => $row['handlingStatus'],
            'acknowledgedAt' => $row['acknowledgedAt'],
            'acknowledgedBy' => $row['acknowledgedBy'],
        ], $this->rows->rows());

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
            foreach (['connection' => 'connection', 'queue' => 'queue'] as $column => $filter) {
                $value = self::filterValue($request, $filter);

                if ($value !== '' && $value !== 'all' && $row[$column] !== $value) {
                    return false;
                }
            }

            $handling = self::filterValue($request, 'handling');

            if ($handling === '') {
                $handling = 'needs_attention';
            }

            if ($handling !== 'all' && $row['handlingStatus'] !== $handling) {
                return false;
            }

            return self::dateRangeMatches(self::stringValue($row['failedAt'] ?? ''), self::filterValue($request, 'from'), self::filterValue($request, 'to'));
        }));
    }
}
