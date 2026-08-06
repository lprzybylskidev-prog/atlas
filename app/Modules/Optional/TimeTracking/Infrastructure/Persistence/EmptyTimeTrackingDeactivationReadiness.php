<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\TimeTrackingDeactivationReadiness;
use App\Modules\Optional\TimeTracking\Application\DTOs\TimeTrackingDeactivationState;
use App\Shared\Application\Modules\ModuleDeactivationRequest;

final readonly class EmptyTimeTrackingDeactivationReadiness implements TimeTrackingDeactivationReadiness
{
    public function forRequest(ModuleDeactivationRequest $request): TimeTrackingDeactivationState
    {
        return new TimeTrackingDeactivationState;
    }
}
