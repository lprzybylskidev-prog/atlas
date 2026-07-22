<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Contracts;

use App\Modules\Core\Exports\Application\DTOs\ReportRenderReadinessResult;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;

interface ReportRenderReadinessProbe
{
    public function reportKey(): string;

    public function check(ReportExportGenerationRequest $request): ReportRenderReadinessResult;
}
