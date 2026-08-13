<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Domain\Events;

enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
