<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\Contracts;

use App\Modules\Optional\Reports\Application\Public\DTOs\ReportExportCleanupResult;
use DateTimeImmutable;

interface ReportExportMaintenance
{
    public function cleanupExpired(DateTimeImmutable $now): ReportExportCleanupResult;
}
