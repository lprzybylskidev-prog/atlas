<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Shared\Application\Tables\AdminTableDefinitions;
use Illuminate\Http\Request;
use Stringable;

final readonly class AdminTimeTrackingDailyDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private UserTimeReportService $reports) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_DAILY;
    }

    public function tableName(): string
    {
        return 'Admin TimeTracking daily summary';
    }

    public function owningModuleKey(): string
    {
        return 'time_tracking';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::ADMIN_DATA_TABLE;
    }

    public function ruleVersion(): string
    {
        return 'admin-time-tracking-daily-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'userPublicId' => 'User public ID',
            'userName' => 'User',
            'userEmail' => 'User email',
            'teamPublicId' => 'Team public ID',
            'teamName' => 'Team',
            'date' => 'Day',
            'countedDuration' => 'Counted total',
            'workDuration' => 'Work',
            'breakDuration' => 'Break',
            'technicalBreakDuration' => 'Break for review',
            'maintenanceDuration' => 'Technical break',
            'otherWorkDuration' => 'Other work',
            'acceptedOtherWorkDuration' => 'Accepted other work',
            'pendingOtherWorkDuration' => 'Other work pending',
            'sessionStatus' => 'Day status',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $report = $this->reports->workTimeForAdminRequest($this->requestFromExport($request));

        foreach ($this->sorted($this->filtered($this->exportRows($report->dailyRows), $request), $request) as $row) {
            yield $row;
        }
    }

    private function requestFromExport(ReportExportGenerationRequest $request): Request
    {
        return Request::create('/admin/work-time/summary/export', 'GET', $this->stringFilters($request));
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
        return array_map(static fn (array $row): array => [
            'publicId' => self::stringValue($row['publicId'] ?? null),
            'userPublicId' => self::stringValue($row['userPublicId'] ?? null),
            'userName' => self::stringValue($row['userName'] ?? null),
            'userEmail' => self::stringValue($row['userEmail'] ?? null),
            'teamPublicId' => self::stringValue($row['teamPublicId'] ?? null),
            'teamName' => self::stringValue($row['teamName'] ?? null),
            'date' => self::stringValue($row['date'] ?? null),
            'countedDuration' => self::stringValue($row['countedDuration'] ?? null),
            'workDuration' => self::stringValue($row['workDuration'] ?? null),
            'breakDuration' => self::stringValue($row['breakDuration'] ?? null),
            'technicalBreakDuration' => self::stringValue($row['technicalBreakDuration'] ?? null),
            'maintenanceDuration' => self::stringValue($row['maintenanceDuration'] ?? null),
            'otherWorkDuration' => self::stringValue($row['otherWorkDuration'] ?? null),
            'acceptedOtherWorkDuration' => self::stringValue($row['acceptedOtherWorkDuration'] ?? null),
            'pendingOtherWorkDuration' => self::stringValue($row['pendingOtherWorkDuration'] ?? null),
            'sessionStatus' => self::stringValue($row['sessionStatus'] ?? null),
        ], $rows);
    }
}
