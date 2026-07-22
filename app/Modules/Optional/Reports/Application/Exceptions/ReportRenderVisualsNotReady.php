<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Exceptions;

use RuntimeException;

final class ReportRenderVisualsNotReady extends RuntimeException
{
    public static function blocked(string $reportKey, string $safeSummary): self
    {
        return new self(sprintf('Report [%s] is not ready for PDF rendering: %s', $reportKey, $safeSummary));
    }
}
