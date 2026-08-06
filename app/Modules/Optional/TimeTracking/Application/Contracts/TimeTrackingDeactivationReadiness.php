<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

use App\Modules\Optional\TimeTracking\Application\DTOs\TimeTrackingDeactivationState;
use App\Shared\Application\Modules\ModuleDeactivationRequest;

interface TimeTrackingDeactivationReadiness
{
    public function forRequest(ModuleDeactivationRequest $request): TimeTrackingDeactivationState;
}
