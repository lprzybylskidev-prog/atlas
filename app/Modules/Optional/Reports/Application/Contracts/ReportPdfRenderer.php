<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Contracts;

interface ReportPdfRenderer
{
    public function render(string $html): string;
}
