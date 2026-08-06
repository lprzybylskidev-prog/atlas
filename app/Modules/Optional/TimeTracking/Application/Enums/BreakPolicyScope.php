<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum BreakPolicyScope: string
{
    case Global = 'global';
    case Team = 'team';
    case UserTeam = 'user_team';
}
