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

final readonly class AdminTimeTrackingCorrectionsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private UserTimeReportService $reports) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_CORRECTIONS;
    }

    public function tableName(): string
    {
        return 'Admin work-time corrections';
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
        return 'admin-work-time-corrections-export-v1';
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
            'type' => 'Type',
            'status' => 'Status',
            'description' => 'Description',
            'requestedAt' => 'Requested',
            'decidedAt' => 'Decided',
            'decisionReason' => 'Decision reason',
            'proposalCount' => 'Proposals',
            'historyCount' => 'History',
            'availableActionsLabel' => 'Available actions',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        foreach ($this->sorted($this->filtered($this->exportRows($this->reports->adminCorrectionRows($this->requestFromExport($request))), $request), $request) as $row) {
            yield $row;
        }
    }

    private function requestFromExport(ReportExportGenerationRequest $request): Request
    {
        return Request::create('/admin/work-time/corrections/export', 'GET', $this->stringFilters($request));
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
            'type' => self::stringValue($row['type'] ?? null),
            'status' => self::stringValue($row['status'] ?? null),
            'description' => self::stringValue($row['description'] ?? null),
            'requestedAt' => self::stringValue($row['requestedAt'] ?? null),
            'decidedAt' => self::stringValue($row['decidedAt'] ?? null),
            'decisionReason' => self::stringValue($row['decisionReason'] ?? null),
            'proposalCount' => is_numeric($row['proposalCount'] ?? null) ? (int) $row['proposalCount'] : 0,
            'historyCount' => is_numeric($row['historyCount'] ?? null) ? (int) $row['historyCount'] : 0,
            'availableActionsLabel' => self::listValue(array_values(array_map(static fn (mixed $action): string => self::stringValue($action), is_array($row['availableActions'] ?? null) ? $row['availableActions'] : []))),
        ], $rows);
    }
}
