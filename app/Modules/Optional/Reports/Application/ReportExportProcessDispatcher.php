<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Modules\Optional\Reports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportGenerationDispatcher;

final readonly class ReportExportProcessDispatcher implements ReportExportGenerationDispatcher
{
    public function __construct(
        private ManagedProcessRunner $processes,
        private ReportExportRequestStore $requests,
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
}
