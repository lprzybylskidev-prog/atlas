<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Rtc;

enum RtcSessionMode: string
{
    case AdHocCall = 'ad_hoc_call';
    case OnlineMeeting = 'online_meeting';
    case HybridMeeting = 'hybrid_meeting';
    case InPersonMeeting = 'in_person_meeting';

    public function supportsRtc(): bool
    {
        return $this !== self::InPersonMeeting;
    }
}
