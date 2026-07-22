<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Contracts;

use App\Modules\Optional\Reports\Application\DTOs\GeneratedReportArtifact;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportGenerationRequest;
use App\Modules\Optional\Reports\Application\Enums\ReportExportFormat;

interface ReportExportGenerator
{
    public function supports(ReportExportFormat $format): bool;

    public function generate(ReportExportGenerationRequest $request): GeneratedReportArtifact;
}
