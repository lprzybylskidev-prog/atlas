<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Shared\Application\Tables\AdminTableDefinitions;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Stringable;

final readonly class TimeTrackingManagerReportDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(
        private UserTimeReportService $reports,
        private ManagerHierarchy $hierarchy,
        private ConnectionInterface $database,
    ) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::TIME_TRACKING_MANAGER_REPORT;
    }

    public function tableName(): string
    {
        return 'TimeTracking manager report';
    }

    public function owningModuleKey(): string
    {
        return 'time_tracking';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'time-tracking-manager-report-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'userPublicId' => 'User public ID',
            'userName' => 'User',
            'userEmail' => 'User email',
            'type' => 'Type',
            'status' => 'Status',
            'context' => 'Context',
            'startedAt' => 'Started at',
            'endedAt' => 'Ended at',
            'duration' => 'Duration',
            'exactSeconds' => 'Exact seconds',
            'reason' => 'Reason',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $teamId = $this->teamId($request);

        if ($teamId < 1 || $request->activeTeamPublicId === null) {
            return;
        }

        $scope = $this->hierarchy->scopeFor($request->activeTeamPublicId, $request->requestingUserPublicId);
        $report = $this->reports->forManagerRequest($this->requestFromExport($request), $teamId, $scope->visibleUserPublicIds);

        foreach ($this->sorted($this->filtered($this->exportRows($report->rows), $request), $request) as $row) {
            yield $row;
        }
    }

    private function teamId(ReportExportGenerationRequest $request): int
    {
        if ($request->activeTeamPublicId === null) {
            return 0;
        }

        $id = $this->database->table(TeamsDatabaseTable::TEAMS)->where('public_id', $request->activeTeamPublicId)->value('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    private function requestFromExport(ReportExportGenerationRequest $request): Request
    {
        return Request::create('/time-tracking/manager-report/export', 'GET', $this->stringFilters($request));
    }

    /**
     * @return array<string, string>
     */
    private function stringFilters(ReportExportGenerationRequest $request): array
    {
        $filters = [];

        foreach ($request->filters as $key => $value) {
            if (is_scalar($value) || $value instanceof Stringable) {
                $filters[$key] = (string) $value;
            }
        }

        return $filters;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, scalar|Stringable|null>>
     */
    private function exportRows(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'publicId' => self::stringValue($row['publicId'] ?? null),
            'userPublicId' => self::stringValue($row['userPublicId'] ?? null),
            'userName' => self::stringValue($row['userName'] ?? null),
            'userEmail' => self::stringValue($row['userEmail'] ?? null),
            'type' => self::stringValue($row['type'] ?? null),
            'status' => self::stringValue($row['status'] ?? null),
            'context' => self::stringValue($row['context'] ?? null),
            'startedAt' => self::stringValue($row['startedAt'] ?? null),
            'endedAt' => self::stringValue($row['endedAt'] ?? null),
            'duration' => $this->duration(is_numeric($row['exactSeconds'] ?? null) ? (int) $row['exactSeconds'] : 0),
            'exactSeconds' => is_numeric($row['exactSeconds'] ?? null) ? (int) $row['exactSeconds'] : 0,
            'reason' => self::stringValue($row['reason'] ?? null),
        ], $rows);
    }

    private function duration(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
