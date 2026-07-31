<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Observability\ApplicationLogReader;

final readonly class AdminApplicationLogsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private ApplicationLogReader $reader) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::APPLICATION_LOGS;
    }

    public function tableName(): string
    {
        return 'Application logs';
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
        return 'admin-application-logs-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'line' => 'Line',
            'occurredAt' => 'Occurred at',
            'level' => 'Level',
            'channel' => 'Channel',
            'environment' => 'Environment',
            'module' => 'Module',
            'source' => 'Source',
            'eventName' => 'Event name',
            'correlationId' => 'Correlation ID',
            'requestId' => 'Request ID',
            'message' => 'Message',
            'details' => 'Details',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $log = $this->reader->latest(fileName: self::filterValue($request, 'file'));
        $rows = array_map(static fn (array $row): array => [
            'publicId' => self::stringValue($row['publicId'] ?? ''),
            'line' => self::stringValue($row['line'] ?? ''),
            'occurredAt' => self::stringValue($row['occurredAt'] ?? ''),
            'level' => self::stringValue($row['level'] ?? ''),
            'channel' => self::stringValue($row['channel'] ?? ''),
            'environment' => self::stringValue($row['environment'] ?? ''),
            'module' => self::stringValue($row['module'] ?? ''),
            'source' => self::stringValue($row['source'] ?? ''),
            'eventName' => self::stringValue($row['eventName'] ?? ''),
            'correlationId' => self::stringValue($row['correlationId'] ?? ''),
            'requestId' => self::stringValue($row['requestId'] ?? ''),
            'message' => self::stringValue($row['message'] ?? ''),
            'details' => self::stringValue($row['details'] ?? ''),
        ], $log['entries']);

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
            foreach (['level' => 'level', 'module' => 'module', 'source' => 'source'] as $column => $filter) {
                $value = self::filterValue($request, $filter);

                if ($value !== '' && $value !== 'all' && $row[$column] !== $value) {
                    return false;
                }
            }

            return self::dateRangeMatches(self::stringValue($row['occurredAt'] ?? ''), self::filterValue($request, 'from'), self::filterValue($request, 'to'));
        }));
    }
}
