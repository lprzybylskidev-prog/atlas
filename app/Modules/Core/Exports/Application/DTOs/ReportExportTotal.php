<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\DTOs;

final readonly class ReportExportTotal
{
    public function __construct(
        public string $label,
        public string $value,
    ) {}
}
