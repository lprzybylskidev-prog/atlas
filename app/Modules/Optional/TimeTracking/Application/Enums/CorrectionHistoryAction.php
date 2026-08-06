<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum CorrectionHistoryAction: string
{
    case Requested = 'requested';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Corrected = 'corrected';
    case ManualEntry = 'manual_entry';
    case ClosedPeriodOverride = 'closed_period_override';
}
