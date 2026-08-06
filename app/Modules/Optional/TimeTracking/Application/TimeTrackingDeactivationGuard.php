<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Optional\TimeTracking\Application\Contracts\TimeTrackingDeactivationReadiness;
use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuard;
use App\Shared\Application\Modules\ModuleDeactivationAssessment;
use App\Shared\Application\Modules\ModuleDeactivationBlocker;
use App\Shared\Application\Modules\ModuleDeactivationRequest;
use App\Shared\Application\Modules\ModuleDeactivationSafeAction;

final readonly class TimeTrackingDeactivationGuard implements ModuleDeactivationGuard
{
    public function __construct(private TimeTrackingDeactivationReadiness $readiness) {}

    public function assess(ModuleDeactivationRequest $request): ModuleDeactivationAssessment
    {
        if ($request->moduleKey->value !== 'time_tracking') {
            return ModuleDeactivationAssessment::allow();
        }

        $state = $this->readiness->forRequest($request);

        if (! $state->hasBlockers()) {
            return ModuleDeactivationAssessment::allow();
        }

        return ModuleDeactivationAssessment::block(
            new ModuleDeactivationBlocker(
                processType: $state->primaryBlockerType(),
                processIdentifier: $request->teamId === null ? 'global' : sprintf('team:%d', $request->teamId),
                reason: $state->blockerSummary(),
            ),
            [
                new ModuleDeactivationSafeAction(
                    action: 'time_tracking.review_open_items',
                    label: 'Review active TimeTracking sessions, pending corrections, maintenance windows, and report jobs before deactivation.',
                ),
            ],
        );
    }
}
