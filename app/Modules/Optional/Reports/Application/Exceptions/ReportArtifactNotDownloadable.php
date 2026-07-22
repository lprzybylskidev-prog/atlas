<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Exceptions;

use RuntimeException;

final class ReportArtifactNotDownloadable extends RuntimeException
{
    public static function blocked(string $publicId): self
    {
        return new self(sprintf('Report artifact [%s] is not available for download.', $publicId));
    }
}
