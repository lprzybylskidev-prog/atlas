<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Contracts;

use App\Modules\Optional\Reports\Application\DTOs\ReportExportGenerationRequest;
use App\Modules\Optional\Reports\Application\DTOs\ReportRenderReadinessResult;

interface ReportRenderReadinessProbe
{
    public function reportKey(): string;

    public function check(ReportExportGenerationRequest $request): ReportRenderReadinessResult;
}
