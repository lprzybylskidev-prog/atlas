<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Core\Exports\Application\DTOs\AuthorizationFingerprint;
use App\Modules\Core\Exports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportGenerationDispatcher;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportRequestRecorder;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportDispatchResult;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Exports\DTOs\AuthorizedReportExportRequest;
use App\Shared\Application\Exports\Enums\ReportExportFormat;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunner;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;

final readonly class ReportExportProcessDispatcher implements ReportExportGenerationDispatcher
{
    public function __construct(
        private ManagedProcessRunner $processes,
        private ReportExportRequestStore $requests,
        private ReportExportRequestRecorder $recorder,
        private ReportExportExecutionPolicy $executionPolicy,
        private ReportExportArtifactGenerator $generator,
        private OperationalModuleGuard $modules,
    ) {}

    public function dispatch(string $requestPublicId, string $actorPublicId, ?string $teamPublicId): string
    {
        $runPublicId = $this->processes->start(
            processKey: ReportExportGenerationProcess::KEY,
            sourceType: 'report_export',
            input: ['export_request_public_id' => $requestPublicId],
            actorPublicId: $actorPublicId,
            teamPublicId: $teamPublicId,
            causationId: $requestPublicId,
        );

        $this->requests->linkProcessRun($requestPublicId, $runPublicId);

        return $runPublicId;
    }

    public function dispatchSnapshot(ReportExportRequestSnapshot $snapshot): ReportExportDispatchResult
    {
        $record = $this->recorder->record($snapshot);
        $availableArtifactPublicId = $this->requests->availableArtifactPublicId($record->publicId);

        if ($availableArtifactPublicId !== null) {
            return new ReportExportDispatchResult(
                exportRequestPublicId: $record->publicId,
                executionMode: 'cached',
                artifactPublicId: $availableArtifactPublicId,
            );
        }

        if ($snapshot->format === ReportExportFormat::BrowserPrint) {
            return new ReportExportDispatchResult(
                exportRequestPublicId: $record->publicId,
                executionMode: 'print',
            );
        }

        if ($this->executionPolicy->canRunSynchronously($snapshot)) {
            $artifactPublicId = $this->generator->generate($record->publicId);

            return new ReportExportDispatchResult(
                exportRequestPublicId: $record->publicId,
                executionMode: 'sync',
                artifactPublicId: $artifactPublicId,
            );
        }

        $runPublicId = $this->dispatch($record->publicId, $snapshot->requestingUserPublicId, $snapshot->activeTeamPublicId);

        return new ReportExportDispatchResult(
            exportRequestPublicId: $record->publicId,
            executionMode: 'queued',
            processRunPublicId: $runPublicId,
        );
    }

    public function dispatchAuthorized(AuthorizedReportExportRequest $request): ReportExportDispatchResult
    {
        $this->modules->ensureAllowed(
            'exports',
            $request->activeTeamPublicId,
            $request->requestingUserPublicId,
            ReportsPermissionCatalog::REQUEST,
        );

        return $this->dispatchSnapshot(new ReportExportRequestSnapshot(
            reportKey: $request->reportKey,
            reportName: $request->reportName,
            moduleKey: $request->moduleKey,
            format: $request->format,
            activeTeamId: $request->activeTeamId,
            activeTeamPublicId: $request->activeTeamPublicId,
            requestingUserId: $request->requestingUserId,
            requestingUserPublicId: $request->requestingUserPublicId,
            filters: $request->filters,
            sorting: $request->sorting,
            visibleColumns: $request->columns,
            columnOrder: $request->columns,
            timeRange: null,
            authorization: new AuthorizationFingerprint(
                moduleKey: $request->moduleKey,
                activeTeamPublicId: $request->activeTeamPublicId,
                requestingUserPublicId: $request->requestingUserPublicId,
                permissionNames: $request->permissionNames,
                allowedColumns: $request->columns,
                ruleVersion: $request->ruleVersion,
            ),
            releaseVersion: $request->releaseVersion,
            ruleVersion: $request->ruleVersion,
            expiresAt: $request->expiresAt,
            locale: $request->locale,
        ));
    }
}
