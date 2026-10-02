<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Meetings;

enum MeetingMode: string
{
    case Online = 'online';
    case InPerson = 'in_person';
    case Hybrid = 'hybrid';

    public function hasRtc(): bool
    {
        return $this !== self::InPerson;
    }

    public function requiresLocation(): bool
    {
        return $this !== self::Online;
    }
}
