<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Exports;

use App\Modules\Optional\Integrations\Infrastructure\Persistence\TableNames\IntegrationsDatabaseTable;
use App\Shared\Application\Exports\AbstractAdminDataTableExportProvider;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\Tables\RegisteredTables;
use Illuminate\Support\Facades\DB;

final readonly class AdminIntegrationRunsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return RegisteredTables::INTEGRATION_RUNS;
    }

    public function tableName(): string
    {
        return 'Integration synchronization runs';
    }

    public function owningModuleKey(): string
    {
        return 'integrations';
    }

    public function requestPermission(): string
    {
        return ExportPermissions::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-integration-runs-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'rowKey' => 'Row key',
            'integrationKey' => 'Integration',
            'operation' => 'Operation',
            'status' => 'Status',
            'startedAt' => 'Started',
            'finishedAt' => 'Finished',
            'correlationId' => 'Correlation',
            'message' => 'Message',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = [];

        foreach (DB::table(IntegrationsDatabaseTable::SYNC_RUNS)
            ->orderByDesc('started_at')
            ->get(['integration_key', 'operation', 'correlation_id', 'status', 'started_at', 'finished_at', 'message']) as $index => $row) {
            $integrationKey = self::stringValue($row->integration_key ?? null);
            $correlationId = self::stringValue($row->correlation_id ?? null);
            $rows[] = [
                'rowKey' => sprintf('%s-%s', $integrationKey === '' ? 'integration' : $integrationKey, $correlationId === '' ? (string) $index : $correlationId),
                'integrationKey' => $integrationKey,
                'operation' => self::stringValue($row->operation ?? null),
                'correlationId' => $correlationId,
                'status' => self::stringValue($row->status ?? null),
                'startedAt' => self::stringValue($row->started_at ?? null),
                'finishedAt' => self::stringValue($row->finished_at ?? null),
                'message' => self::stringValue($row->message ?? null),
            ];
        }

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
            yield $row;
        }
    }
}
