<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum CorrectionRequestStatus: string
{
    case Pending = 'pending';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Corrected = 'corrected';
}
