<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Enums;

enum ReportExportStatus: string
{
    case Requested = 'requested';
    case Queued = 'queued';
    case Generating = 'generating';
    case Available = 'available';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
