<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use App\Modules\Optional\Reports\Application\Enums\ReportExportStatus;
use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuard;
use App\Shared\Application\Modules\ModuleDeactivationAssessment;
use App\Shared\Application\Modules\ModuleDeactivationBlocker;
use App\Shared\Application\Modules\ModuleDeactivationRequest;
use App\Shared\Application\Modules\ModuleDeactivationSafeAction;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;

final readonly class ReportsDeactivationGuard implements ModuleDeactivationGuard
{
    public function __construct(private ConnectionInterface $database) {}

    public function assess(ModuleDeactivationRequest $request): ModuleDeactivationAssessment
    {
        $reportRequest = $this->database->table(DatabaseTable::REPORT_EXPORT_REQUESTS)
            ->where('module_key', $request->moduleKey->value)
            ->whereIn('status', [
                ReportExportStatus::Requested->value,
                ReportExportStatus::Queued->value,
                ReportExportStatus::Generating->value,
            ])
            ->when($request->teamId !== null, static fn ($query) => $query->where('team_id', $request->teamId))
            ->orderByDesc('created_at')
            ->first();

        if (! is_object($reportRequest)) {
            return ModuleDeactivationAssessment::allow();
        }

        return ModuleDeactivationAssessment::block(
            new ModuleDeactivationBlocker(
                processType: 'report_export',
                processIdentifier: $this->stringValue($reportRequest->public_id ?? null),
                reason: sprintf(
                    'Report/export request %s for module %s is %s.',
                    $this->stringValue($reportRequest->public_id ?? null),
                    $request->moduleKey->value,
                    $this->stringValue($reportRequest->status ?? null),
                ),
            ),
            [new ModuleDeactivationSafeAction('reports.review_exports', 'Wait for report/export generation to finish or cancel the request before deactivation.')],
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : 'unknown';
    }
}
