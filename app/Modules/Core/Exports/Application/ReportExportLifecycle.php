<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Core\Exports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportRequestRecorder;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportRequestRecord;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;

final readonly class ReportExportLifecycle implements ReportExportRequestRecorder
{
    public function __construct(
        private ReportExportRequestStore $requests,
        private ReportExportExecutionPolicy $executionPolicy,
        private OperationalModuleGuard $modules,
    ) {}

    public function record(ReportExportRequestSnapshot $snapshot): ReportExportRequestRecord
    {
        $this->authorize($snapshot);

        return $this->requests->createFromSnapshot($this->snapshotWithSafeExecutionFlag($snapshot));
    }

    private function authorize(ReportExportRequestSnapshot $snapshot): void
    {
        $this->modules->ensureAllowed('exports', $snapshot->activeTeamPublicId, $snapshot->requestingUserPublicId, ReportsPermissionCatalog::REQUEST);
        $this->modules->ensureAllowed($snapshot->moduleKey, $snapshot->activeTeamPublicId, $snapshot->requestingUserPublicId);

        if ($snapshot->auditExport) {
            $this->modules->ensureAllowed('exports', $snapshot->activeTeamPublicId, $snapshot->requestingUserPublicId, ReportsPermissionCatalog::AUDIT_EXPORT);
        }
    }

    private function snapshotWithSafeExecutionFlag(ReportExportRequestSnapshot $snapshot): ReportExportRequestSnapshot
    {
        return new ReportExportRequestSnapshot(
            reportKey: $snapshot->reportKey,
            reportName: $snapshot->reportName,
            moduleKey: $snapshot->moduleKey,
            format: $snapshot->format,
            activeTeamId: $snapshot->activeTeamId,
            activeTeamPublicId: $snapshot->activeTeamPublicId,
            requestingUserId: $snapshot->requestingUserId,
            requestingUserPublicId: $snapshot->requestingUserPublicId,
            filters: $snapshot->filters,
            sorting: $snapshot->sorting,
            visibleColumns: $snapshot->visibleColumns,
            columnOrder: $snapshot->columnOrder,
            timeRange: $snapshot->timeRange,
            authorization: $snapshot->authorization,
            releaseVersion: $snapshot->releaseVersion,
            ruleVersion: $snapshot->ruleVersion,
            expiresAt: $snapshot->expiresAt,
            synchronousAllowed: $this->executionPolicy->canRunSynchronously($snapshot),
            auditExport: $snapshot->auditExport,
            estimatedRowCount: $snapshot->estimatedRowCount,
        );
    }
}
