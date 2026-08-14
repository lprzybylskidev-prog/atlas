<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Realtime;

enum ManualStatus: string
{
    case Available = 'available';
    case Busy = 'busy';
    case DoNotDisturb = 'do_not_disturb';
    case OutOfOffice = 'out_of_office';
}
