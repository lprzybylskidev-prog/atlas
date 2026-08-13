<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Domain\Events;

enum CalendarAvailability: string
{
    case Busy = 'busy';
    case Free = 'free';
}
