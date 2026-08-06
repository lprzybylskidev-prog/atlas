<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum WorkSessionClosureReason: string
{
    case Logout = 'logout';
    case TeamSwitched = 'team_switched';
    case TeamUntracked = 'team_untracked';
    case SessionSuperseded = 'session_superseded';
    case ModuleUnavailable = 'module_unavailable';
    case BreakMaximumDuration = 'break_maximum_duration';
    case Inactivity = 'inactivity';
    case AdministrativeTermination = 'administrative_termination';
}
