<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Contracts;

use App\Modules\Core\Exports\Application\DTOs\ReportChartDefinition;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;

interface ReportChartProvider
{
    public function reportKey(): string;

    /**
     * @return list<ReportChartDefinition>
     */
    public function charts(ReportExportGenerationRequest $request): array;
}
