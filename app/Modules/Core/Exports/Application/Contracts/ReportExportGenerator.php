<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Contracts;

use App\Modules\Core\Exports\Application\DTOs\GeneratedReportArtifact;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\Enums\ReportExportFormat;

interface ReportExportGenerator
{
    public function supports(ReportExportFormat $format): bool;

    public function generate(ReportExportGenerationRequest $request): GeneratedReportArtifact;
}
