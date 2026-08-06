<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum SettlementPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
