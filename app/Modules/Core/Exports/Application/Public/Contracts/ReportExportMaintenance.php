<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\Contracts;

use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportCleanupResult;
use DateTimeImmutable;

interface ReportExportMaintenance
{
    public function cleanupExpired(DateTimeImmutable $now): ReportExportCleanupResult;
}
