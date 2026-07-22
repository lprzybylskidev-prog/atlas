<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\DTOs;

final readonly class ReportExportTotal
{
    public function __construct(
        public string $label,
        public string $value,
    ) {}
}
