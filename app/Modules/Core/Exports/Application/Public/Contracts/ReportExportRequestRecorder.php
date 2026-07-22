<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\Contracts;

use App\Modules\Core\Exports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportRequestRecord;

interface ReportExportRequestRecorder
{
    public function record(ReportExportRequestSnapshot $snapshot): ReportExportRequestRecord;
}
