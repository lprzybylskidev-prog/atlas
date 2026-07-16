<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Activation;

enum ModuleActivationScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Applied = 'applied';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
