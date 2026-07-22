<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\DTOs;

final readonly class ReportExportColumn
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}
