<?php

declare(strict_types=1);

namespace Tests\Unit\TimeTracking;

use App\Modules\Optional\TimeTracking\Application\Contracts\TimeTrackingDeactivationReadiness;
use App\Modules\Optional\TimeTracking\Application\DTOs\TimeTrackingDeactivationState;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingDeactivationGuard;
use App\Shared\Application\Modules\ModuleDeactivationRequest;
use App\Shared\Application\Modules\ModuleKey;
use PHPUnit\Framework\TestCase;

final class TimeTrackingDeactivationGuardTest extends TestCase
{
    public function test_it_ignores_other_modules(): void
    {
        $guard = new TimeTrackingDeactivationGuard(new FixedReadiness(new TimeTrackingDeactivationState(activeWorkSessions: 1)));

        $assessment = $guard->assess(new ModuleDeactivationRequest(
            moduleKey: new ModuleKey('reports'),
            teamId: null,
            requestedBy: 'admin@example.test',
        ));

        self::assertTrue($assessment->canDeactivate());
    }

    public function test_it_allows_time_tracking_deactivation_when_no_open_items_exist(): void
    {
        $guard = new TimeTrackingDeactivationGuard(new FixedReadiness(new TimeTrackingDeactivationState));

        $assessment = $guard->assess($this->request());

        self::assertTrue($assessment->canDeactivate());
    }

    public function test_it_blocks_time_tracking_deactivation_when_open_items_exist(): void
    {
        $guard = new TimeTrackingDeactivationGuard(new FixedReadiness(new TimeTrackingDeactivationState(
            activeWorkSessions: 2,
            activeBreaks: 1,
            activeOtherWork: 1,
            activeMaintenanceWindows: 1,
            pendingCorrections: 3,
            unsafeReportJobs: 1,
        )));

        $assessment = $guard->assess($this->request(teamId: 42));

        self::assertFalse($assessment->canDeactivate());
        self::assertCount(1, $assessment->blockers);
        self::assertSame('time_tracking.active_work', $assessment->blockers[0]->processType);
        self::assertSame('team:42', $assessment->blockers[0]->processIdentifier);
        self::assertStringContainsString('pending corrections: 3', $assessment->blockers[0]->reason);
        self::assertSame('time_tracking.review_open_items', $assessment->safeActions[0]->action);
    }

    private function request(?int $teamId = null): ModuleDeactivationRequest
    {
        return new ModuleDeactivationRequest(
            moduleKey: new ModuleKey('time_tracking'),
            teamId: $teamId,
            requestedBy: 'admin@example.test',
        );
    }
}

final readonly class FixedReadiness implements TimeTrackingDeactivationReadiness
{
    public function __construct(private TimeTrackingDeactivationState $state) {}

    public function forRequest(ModuleDeactivationRequest $request): TimeTrackingDeactivationState
    {
        return $this->state;
    }
}
