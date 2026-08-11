<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Exports;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Shared\Application\Exports\AbstractAdminDataTableExportProvider;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\Tables\RegisteredTables;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Http\Request;
use Stringable;

final readonly class TimeTrackingUserReportDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(
        private UserTimeReportService $reports,
        private UserLookup $users,
        private TeamLookup $teams,
    ) {}

    public function tableKey(): string
    {
        return RegisteredTables::TIME_TRACKING_USER_REPORT;
    }

    public function tableName(): string
    {
        return 'TimeTracking user report';
    }

    public function owningModuleKey(): string
    {
        return 'time_tracking';
    }

    public function requestPermission(): string
    {
        return ExportPermissions::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'time-tracking-user-report-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
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
        $userId = $this->userId($request->requestingUserPublicId);
        $teamId = $this->teamId($request);

        if ($userId < 1 || $teamId < 1) {
            return;
        }

        $report = $this->reports->forRequest($this->requestFromExport($request), $userId, $teamId);

        foreach ($this->sorted($this->filtered($this->exportRows($report->rows), $request), $request) as $row) {
            yield $row;
        }
    }

    private function userId(string $publicId): int
    {
        return $this->users->internalIdForPublicId($publicId) ?? 0;
    }

    private function teamId(ReportExportGenerationRequest $request): int
    {
        if ($request->activeTeamPublicId === null) {
            return 0;
        }

        return $this->teams->internalIdForPublicId($request->activeTeamPublicId) ?? 0;
    }

    private function requestFromExport(ReportExportGenerationRequest $request): Request
    {
        return Request::create('/user/work-time/export', 'GET', $this->stringFilters($request));
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
