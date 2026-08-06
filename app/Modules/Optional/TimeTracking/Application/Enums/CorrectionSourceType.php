<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum CorrectionSourceType: string
{
    case WorkSession = 'work_session';
    case Break = 'break';
    case OtherWork = 'other_work';
}
