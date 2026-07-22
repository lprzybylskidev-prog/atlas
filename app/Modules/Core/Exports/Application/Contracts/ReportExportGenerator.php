<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Contracts;

use App\Modules\Core\Exports\Application\DTOs\GeneratedReportArtifact;
use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;

interface ReportExportGenerator
{
    public function supports(ReportExportFormat $format): bool;

    public function generate(ReportExportGenerationRequest $request): GeneratedReportArtifact;
}
