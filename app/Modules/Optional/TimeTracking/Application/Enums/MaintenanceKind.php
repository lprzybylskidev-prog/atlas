<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Enums;

enum MaintenanceKind: string
{
    case Scheduled = 'scheduled';
    case Emergency = 'emergency';
}
