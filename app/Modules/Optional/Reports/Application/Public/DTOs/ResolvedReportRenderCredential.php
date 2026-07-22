<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\DTOs;

use App\Modules\Optional\Reports\Application\DTOs\ReportExportGenerationRequest;

final readonly class ResolvedReportRenderCredential
{
    public function __construct(
        public string $publicId,
        public ReportExportGenerationRequest $request,
    ) {}
}
