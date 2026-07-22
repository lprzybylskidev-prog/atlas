<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\DTOs;

final readonly class ReportExportColumn
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}
