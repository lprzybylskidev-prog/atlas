<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Rtc;

use App\Modules\Optional\Chat\Application\Contracts\RtcSessionAccessAuthorizer;
use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;
use App\Modules\Optional\Chat\Application\Exceptions\RtcAccessDenied;

final class DenyAllRtcSessionAccessAuthorizer implements RtcSessionAccessAuthorizer
{
    public function authorize(string $sessionPublicId, string $userPublicId, string $activeTeamPublicId): RtcSessionAdmission
    {
        throw RtcAccessDenied::sessionUnavailable();
    }
}
