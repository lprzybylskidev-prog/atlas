<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\Integrations\Application\Public\Persistence\IntegrationsDatabaseTable;
use App\Shared\Application\Tables\AdminTableDefinitions;
use Illuminate\Support\Facades\DB;

final readonly class AdminIntegrationRunsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::INTEGRATION_RUNS;
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
        return ReportsPermissionCatalog::REQUEST;
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
