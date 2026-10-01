<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;

interface RtcSessionAccessAuthorizer
{
    public function authorize(string $sessionPublicId, string $userPublicId, string $activeTeamPublicId): RtcSessionAdmission;
}
