<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum CorrectionRequestType: string
{
    case Descriptive = 'descriptive';
    case ExactChange = 'exact_change';
    case ManualEntry = 'manual_entry';
    case ClosedPeriodOverride = 'closed_period_override';
}
