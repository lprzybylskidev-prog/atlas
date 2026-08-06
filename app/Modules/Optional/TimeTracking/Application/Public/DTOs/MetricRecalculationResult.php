<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\DTOs;

final readonly class MetricRecalculationResult
{
    /**
     * @param  list<DerivedMetricResult>  $results
     */
    public function __construct(
        public MetricDefinitionSnapshot $definitionSnapshot,
        public array $results,
    ) {}
}
