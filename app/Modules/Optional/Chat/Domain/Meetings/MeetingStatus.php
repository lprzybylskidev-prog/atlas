<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Meetings;

enum MeetingStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
}
