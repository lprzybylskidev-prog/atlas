<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Exceptions;

use App\Modules\Optional\Reports\Application\Enums\ReportExportFormat;
use RuntimeException;

final class ReportExportGeneratorMissing extends RuntimeException
{
    public static function forFormat(ReportExportFormat $format): self
    {
        return new self(sprintf('Report export generator [%s] is not registered.', $format->value));
    }
}
