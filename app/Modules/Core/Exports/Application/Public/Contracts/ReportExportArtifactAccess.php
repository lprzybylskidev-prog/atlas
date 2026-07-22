<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\Contracts;

use App\Modules\Core\Exports\Application\Public\DTOs\DownloadableReportArtifact;

interface ReportExportArtifactAccess
{
    public function download(string $artifactPublicId, string $actorPublicId, ?string $activeTeamPublicId): DownloadableReportArtifact;
}
