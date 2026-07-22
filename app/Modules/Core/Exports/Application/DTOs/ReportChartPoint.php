<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\DTOs;

final readonly class ReportChartPoint
{
    public function __construct(
        public string $label,
        public int|float $value,
    ) {}
}
