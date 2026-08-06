<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum OtherWorkClosureReason: string
{
    case Normal = 'normal';
    case Forced = 'forced';
}
