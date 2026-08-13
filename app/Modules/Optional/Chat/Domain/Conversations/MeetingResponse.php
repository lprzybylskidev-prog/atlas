<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Conversations;

enum MeetingResponse: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
