<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\DTOs;

final readonly class ReportChartSeries
{
    /**
     * @param  list<ReportChartPoint>  $points
     */
    public function __construct(
        public string $label,
        public array $points,
    ) {}
}
