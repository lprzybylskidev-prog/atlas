<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Meetings;

enum MeetingRole: string
{
    case Organizer = 'organizer';
    case Participant = 'participant';
}
