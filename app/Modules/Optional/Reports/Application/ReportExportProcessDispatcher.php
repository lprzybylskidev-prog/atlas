<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Modules\Optional\Reports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Optional\Reports\Application\Enums\ReportExportFormat;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportGenerationDispatcher;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportRequestRecorder;
use App\Modules\Optional\Reports\Application\Public\DTOs\ReportExportDispatchResult;

final readonly class ReportExportProcessDispatcher implements ReportExportGenerationDispatcher
{
    public function __construct(
        private ManagedProcessRunner $processes,
        private ReportExportRequestStore $requests,
        private ReportExportRequestRecorder $recorder,
        private ReportExportExecutionPolicy $executionPolicy,
        private ReportExportArtifactGenerator $generator,
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

        if ($this->executionPolicy->canRunSynchronously($snapshot) && $snapshot->format !== ReportExportFormat::BrowserPrint) {
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
}
