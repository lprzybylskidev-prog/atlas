<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Contracts;

use App\Modules\Optional\Reports\Application\DTOs\ReportChartDefinition;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportGenerationRequest;

interface ReportChartProvider
{
    public function reportKey(): string;

    /**
     * @return list<ReportChartDefinition>
     */
    public function charts(ReportExportGenerationRequest $request): array;
}
