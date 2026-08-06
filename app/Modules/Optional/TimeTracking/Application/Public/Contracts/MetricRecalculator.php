<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\Contracts;

use App\Modules\Optional\TimeTracking\Application\Public\DTOs\MetricRecalculationRequest;
use App\Modules\Optional\TimeTracking\Application\Public\DTOs\MetricRecalculationResult;

interface MetricRecalculator
{
    public function recalculate(MetricRecalculationRequest $request): MetricRecalculationResult;
}
