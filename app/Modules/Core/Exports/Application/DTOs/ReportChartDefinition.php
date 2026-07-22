<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\DTOs;

final readonly class ReportChartDefinition
{
    /**
     * @param  list<ReportChartSeries>  $series
     */
    public function __construct(
        public string $key,
        public string $title,
        public ?string $description,
        public ?string $unit,
        public array $series,
    ) {}
}
