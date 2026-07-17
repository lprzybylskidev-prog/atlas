<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers;

use App\Modules\Core\Health\Application\Readiness\Contracts\ReadinessChecker;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDenialReason;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Infrastructure\Observability\ModuleActivationScheduleDiagnostics;
use App\Shared\Infrastructure\Observability\SchedulerHeartbeatMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminSystemStatusController
{
    public function __construct(
        private ModuleGate $moduleGate,
        private ReadinessChecker $readiness,
        private SchedulerHeartbeatMonitor $schedulerHeartbeat,
        private ModuleActivationScheduleDiagnostics $moduleActivationDiagnostics,
    ) {}

    public function __invoke(Request $request): Response
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $userPublicId = is_string($userPublicId) ? $userPublicId : null;
        $teamPublicId = is_string($teamPublicId) ? $teamPublicId : null;

        return Inertia::render('Admin/SystemStatus', [
            'availability' => [
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.identity',
                    request: new ModuleAccessRequest(
                        moduleKey: 'identity',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status',
                    ),
                ),
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.search',
                    request: new ModuleAccessRequest(
                        moduleKey: 'search',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status',
                    ),
                ),
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.readiness',
                    request: new ModuleAccessRequest(
                        moduleKey: 'health',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status.readiness',
                    ),
                ),
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.scheduler',
                    request: new ModuleAccessRequest(
                        moduleKey: 'authorization',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status.scheduler',
                    ),
                ),
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.module-activation',
                    request: new ModuleAccessRequest(
                        moduleKey: 'authorization',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status.module-activation',
                    ),
                ),
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.release',
                    request: new ModuleAccessRequest(
                        moduleKey: 'health',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status.release',
                    ),
                ),
            ],
        ]);
    }

    public function release(): JsonResponse
    {
        $deployedAt = config('atlas.release.deployed_at');
        $deployedBy = config('atlas.release.deployed_by');
        $source = config('atlas.release.source');

        return response()->json([
            'data' => [
                'label' => 'Release',
                'value' => config()->string('atlas.release.version'),
                'description' => 'Application release identity and last deployment metadata.',
                'status' => 'healthy',
                'releaseVersion' => config()->string('atlas.release.version'),
                'releaseId' => config()->string('atlas.release.id'),
                'environment' => config()->string('app.env'),
                'deployedAt' => is_scalar($deployedAt) ? (string) $deployedAt : null,
                'deployedBy' => is_scalar($deployedBy) ? (string) $deployedBy : null,
                'deploySource' => is_scalar($source) ? (string) $source : null,
            ],
            'empty' => false,
        ]);
    }

    public function readiness(): JsonResponse
    {
        $report = $this->readiness->check();
        $payload = $report->toAdminArray();

        return response()->json([
            'data' => [
                'label' => 'Readiness',
                'value' => ucfirst($payload['status']),
                'description' => sprintf(
                    '%d blocking failure(s), %d degraded dependency issue(s).',
                    $payload['blocking']['failed'],
                    $payload['degraded']['failed'],
                ),
                'status' => $payload['status'],
                'checkedAt' => $payload['checked_at'],
                'blockingFailed' => $payload['blocking']['failed'],
                'blockingTotal' => $payload['blocking']['total'],
                'degradedFailed' => $payload['degraded']['failed'],
                'degradedTotal' => $payload['degraded']['total'],
                'checks' => $payload['checks'],
            ],
            'empty' => false,
        ]);
    }

    public function scheduler(): JsonResponse
    {
        $status = $this->schedulerHeartbeat->status();

        return response()->json([
            'data' => [
                'label' => 'Scheduler',
                'value' => $status['label'],
                'description' => $status['description'],
                'status' => $status['status'],
                'lastSuccessAt' => $status['lastSuccessAt'],
                'lastStartedAt' => $status['lastStartedAt'],
                'lastFinishedAt' => $status['lastFinishedAt'],
                'lastRuntimeMs' => $status['lastRuntimeMs'],
                'lastError' => $status['lastError'],
                'staleAfterSeconds' => $status['staleAfterSeconds'],
            ],
            'empty' => false,
        ]);
    }

    public function moduleActivation(): JsonResponse
    {
        $status = $this->moduleActivationDiagnostics->status();

        return response()->json([
            'data' => [
                'label' => 'Module activation',
                'value' => $status['label'],
                'description' => $status['description'],
                'status' => $status['status'],
                'failedCount' => $status['failedCount'],
                'latestFailedPublicId' => $status['latestFailedPublicId'],
                'latestFailedModule' => $status['latestFailedModule'],
                'latestFailedAt' => $status['latestFailedAt'],
                'latestFailureReason' => $status['latestFailureReason'],
            ],
            'empty' => false,
        ]);
    }

    /**
     * @return array{elementKey: string, reason: string}
     */
    private function availabilityEntry(string $elementKey, ModuleAccessRequest $request): array
    {
        $decision = $this->moduleGate->inspect($request);

        return [
            'elementKey' => $elementKey,
            'reason' => match ($decision->denialReason) {
                null => 'available',
                ModuleAccessDenialReason::PermissionDenied => 'permission-denied',
                ModuleAccessDenialReason::InvalidActiveTeam => 'active-team-required',
                default => 'module-inactive',
            },
        ];
    }
}
