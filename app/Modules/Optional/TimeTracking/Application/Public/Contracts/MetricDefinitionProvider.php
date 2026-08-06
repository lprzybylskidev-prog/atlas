<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\Contracts;

use App\Modules\Optional\TimeTracking\Application\Public\DTOs\MetricDefinition;

interface MetricDefinitionProvider
{
    /**
     * @return list<MetricDefinition>
     */
    public function definitions(): array;
}
