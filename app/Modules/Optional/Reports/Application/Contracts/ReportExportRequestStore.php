<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Contracts;

use App\Modules\Optional\Reports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Optional\Reports\Application\Public\DTOs\ReportExportRequestRecord;

interface ReportExportRequestStore
{
    public function createFromSnapshot(ReportExportRequestSnapshot $snapshot): ReportExportRequestRecord;

    public function linkProcessRun(string $requestPublicId, string $processRunPublicId): void;

    public function markGenerating(string $requestPublicId): void;

    public function markFailed(string $requestPublicId, string $safeErrorSummary): void;
}
