<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum OtherWorkCategoryScope: string
{
    case Global = 'global';
    case Team = 'team';
}
