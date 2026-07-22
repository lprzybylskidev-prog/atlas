<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Exceptions;

use RuntimeException;

final class ReportExportDataProviderMissing extends RuntimeException
{
    public static function forReport(string $reportKey): self
    {
        return new self(sprintf('Report data provider [%s] is not registered.', $reportKey));
    }
}
