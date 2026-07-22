<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use App\Modules\Optional\Reports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportRequestRecorder;
use App\Modules\Optional\Reports\Application\Public\DTOs\ReportExportRequestRecord;

final readonly class ReportExportLifecycle implements ReportExportRequestRecorder
{
    public function __construct(private ReportExportRequestStore $requests) {}

    public function record(ReportExportRequestSnapshot $snapshot): ReportExportRequestRecord
    {
        return $this->requests->createFromSnapshot($snapshot);
    }
}
