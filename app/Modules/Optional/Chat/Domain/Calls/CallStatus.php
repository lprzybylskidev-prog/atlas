<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Calls;

enum CallStatus: string
{
    case Ringing = 'ringing';
    case Active = 'active';
    case Ended = 'ended';
    case Declined = 'declined';
    case Busy = 'busy';
    case Missed = 'missed';
    case Failed = 'failed';

    public function acceptsParticipants(): bool
    {
        return $this === self::Ringing || $this === self::Active;
    }
}
