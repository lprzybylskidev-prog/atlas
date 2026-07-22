<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\Contracts;

use App\Modules\Optional\Reports\Application\Public\DTOs\DownloadableReportArtifact;

interface ReportExportArtifactAccess
{
    public function download(string $artifactPublicId, string $actorPublicId, ?string $activeTeamPublicId): DownloadableReportArtifact;
}
