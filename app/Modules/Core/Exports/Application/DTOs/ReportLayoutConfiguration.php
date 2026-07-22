<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\DTOs;

final readonly class ReportLayoutConfiguration
{
    public function __construct(
        public string $companyName,
        public string $footerText,
        public ?string $logoDataUri,
    ) {}
}
