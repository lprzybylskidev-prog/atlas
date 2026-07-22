<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Exceptions;

use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use RuntimeException;

final class ReportExportGeneratorMissing extends RuntimeException
{
    public static function forFormat(ReportExportFormat $format): self
    {
        return new self(sprintf('Report export generator [%s] is not registered.', $format->value));
    }
}
