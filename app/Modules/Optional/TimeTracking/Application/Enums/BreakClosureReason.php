<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum BreakClosureReason: string
{
    case Normal = 'normal';
    case Forced = 'forced';
    case MaximumDuration = 'maximum_duration';
}
