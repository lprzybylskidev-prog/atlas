<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum MaintenanceStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
}
