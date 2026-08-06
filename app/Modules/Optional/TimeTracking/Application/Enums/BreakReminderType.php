<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum BreakReminderType: string
{
    case BeforeMaximum = 'before_maximum';
}
