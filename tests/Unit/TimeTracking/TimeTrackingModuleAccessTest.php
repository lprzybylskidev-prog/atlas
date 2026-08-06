<?php

declare(strict_types=1);

namespace Tests\Unit\TimeTracking;

use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessDenialReason;
use App\Shared\Application\Modules\ModuleAccessRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TimeTrackingModuleAccessTest extends TestCase
{
    public function test_it_builds_canonical_time_tracking_module_gate_requests(): void
    {
        $gate = new RecordingModuleGate(ModuleAccessDecision::allow());

        (new TimeTrackingModuleAccess($gate))->ensureAllowed(
            activeTeamId: 42,
            activeTeamPublicId: '01K1J7APZKQ63CJS7HZAH4NX2M',
            userPublicId: '01K1J79A0Q9Q70R99P9GF7K8DX',
            requiredPermission: 'time-tracking.reports.view',
        );

        self::assertNotNull($gate->lastRequest);
        self::assertSame('time_tracking', $gate->lastRequest->moduleKey);
        self::assertSame(42, $gate->lastRequest->activeTeamId);
        self::assertSame('01K1J7APZKQ63CJS7HZAH4NX2M', $gate->lastRequest->activeTeamPublicId);
        self::assertSame('01K1J79A0Q9Q70R99P9GF7K8DX', $gate->lastRequest->userPublicId);
        self::assertSame('time-tracking.reports.view', $gate->lastRequest->requiredPermission);
    }

    public function test_it_throws_when_module_gate_denies_time_tracking_access(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TimeTracking module access denied: team_inactive.');

        (new TimeTrackingModuleAccess(new RecordingModuleGate(ModuleAccessDecision::deny(ModuleAccessDenialReason::TeamInactive))))
            ->ensureAllowed(activeTeamId: 42);
    }

    public function test_it_exposes_boolean_module_gate_decision_for_optional_synchronizers(): void
    {
        self::assertFalse((new TimeTrackingModuleAccess(new RecordingModuleGate(ModuleAccessDecision::deny(ModuleAccessDenialReason::GloballyInactive))))->allows());
        self::assertTrue((new TimeTrackingModuleAccess(new RecordingModuleGate(ModuleAccessDecision::allow())))->allows());
    }
}

final class RecordingModuleGate implements ModuleGate
{
    public ?ModuleAccessRequest $lastRequest = null;

    public function __construct(private readonly ModuleAccessDecision $decision) {}

    public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
    {
        $this->lastRequest = $request;

        return $this->decision;
    }

    public function allows(ModuleAccessRequest $request): bool
    {
        $this->lastRequest = $request;

        return $this->decision->allowed;
    }
}
