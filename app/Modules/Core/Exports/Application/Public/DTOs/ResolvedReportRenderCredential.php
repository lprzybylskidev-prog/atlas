<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\DTOs;

final readonly class ResolvedReportRenderCredential
{
    public function __construct(
        public string $publicId,
        public ReportExportGenerationRequest $request,
    ) {}
}
