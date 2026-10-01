<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Calls;

enum CallParticipantState: string
{
    case Ringing = 'ringing';
    case Notified = 'notified';
    case Joined = 'joined';
    case Declined = 'declined';
    case Busy = 'busy';
    case Missed = 'missed';
    case Left = 'left';
    case Failed = 'failed';

    public function canJoin(): bool
    {
        return in_array($this, [self::Ringing, self::Notified, self::Left, self::Failed], true);
    }
}
