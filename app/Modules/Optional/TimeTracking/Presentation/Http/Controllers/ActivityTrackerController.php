<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\ImpersonationSessionState;
use App\Modules\Core\Identity\Application\Public\Contracts\ImpersonationSimulationRecorder;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionLimitResolver;
use App\Modules\Optional\TimeTracking\Application\DTOs\InactivityPolicy;
use App\Modules\Optional\TimeTracking\Application\InactivityCoordinator;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ActivityTrackerController
{
    public function __construct(
        private InactivityCoordinator $inactivity,
        private ImpersonationSessionState $impersonation,
        private ImpersonationSimulationRecorder $simulation,
        private UserSessionLimitResolver $sessionLimits,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'inactive_ms' => ['required', 'integer', 'min:0', 'max:86400000'],
        ]);
        $userId = $this->userId($request);
        $policy = $this->policyFor($request);

        if ($this->impersonation->active($request)) {
            $this->recordSimulation($request, $policy);

            return response()->json([
                'status' => 'active',
                'workEnded' => false,
                'simulated' => true,
                'thresholdSeconds' => $policy->inactivityThresholdSeconds,
                'warningSeconds' => $policy->warningSeconds,
            ]);
        }

        if ($userId === null) {
            return response()->json([
                'status' => 'inactive',
                'thresholdSeconds' => $policy->inactivityThresholdSeconds,
                'warningSeconds' => $policy->warningSeconds,
            ]);
        }

        $observedAt = new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw'));
        $inactiveSeconds = (int) floor($request->integer('inactive_ms') / 1000);
        $lastActivityAt = $observedAt->sub(new DateInterval('PT'.$inactiveSeconds.'S'));
        $decision = $this->inactivity->evaluate($userId, $lastActivityAt, $observedAt, $policy);

        return response()->json([
            'status' => $decision->workEnded ? 'ended' : ($observedAt >= $decision->warningStartsAt ? 'warning' : 'active'),
            'workEnded' => $decision->workEnded,
            'warningStartsAt' => $decision->warningStartsAt->format(DateTimeImmutable::ATOM),
            'warningEndsAt' => $decision->warningEndsAt->format(DateTimeImmutable::ATOM),
            'thresholdSeconds' => $policy->inactivityThresholdSeconds,
            'warningSeconds' => $policy->warningSeconds,
        ]);
    }

    private function userId(Request $request): ?int
    {
        $id = data_get($request->user(), 'id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function policyFor(Request $request): InactivityPolicy
    {
        $userId = $this->userId($request);
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if ($userId !== null && is_string($teamPublicId)) {
            $limits = $this->sessionLimits->limitsForUserId($userId, $teamPublicId);

            return new InactivityPolicy(max(60, $limits['inactivity'] * 60));
        }

        return new InactivityPolicy;
    }

    private function recordSimulation(Request $request, InactivityPolicy $policy): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $sessionId = $this->impersonation->sessionId($request);

        if ($sessionId === null) {
            return;
        }

        $this->simulation->put($sessionId, 'time-tracking.activity', [
            'inactive_ms' => $request->integer('inactive_ms'),
            'observed_at' => now('Europe/Warsaw')->toIso8601String(),
            'threshold_seconds' => $policy->inactivityThresholdSeconds,
            'warning_seconds' => $policy->warningSeconds,
        ]);
    }
}
