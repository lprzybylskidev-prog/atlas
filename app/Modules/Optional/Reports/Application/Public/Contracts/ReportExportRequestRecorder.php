<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\Contracts;

use App\Modules\Optional\Reports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Optional\Reports\Application\Public\DTOs\ReportExportRequestRecord;

interface ReportExportRequestRecorder
{
    public function record(ReportExportRequestSnapshot $snapshot): ReportExportRequestRecord;
}
