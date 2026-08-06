<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Inertia;

use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionLimitResolver;
use App\Modules\Optional\TimeTracking\Application\DTOs\InactivityPolicy;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use App\Shared\Presentation\Inertia\Contracts\InertiaSharedDataContributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class TimeTrackingInertiaData implements InertiaSharedDataContributor
{
    public function __construct(
        private EffectivePermissionChecker $permissions,
        private UserSessionLimitResolver $sessionLimits,
    ) {}

    public function key(): string
    {
        return 'optional.time-tracking';
    }

    public function data(Request $request): array
    {
        return [
            'timeTracking.activity' => $this->activity($request),
        ];
    }

    /**
     * @return array{enabled: bool, endpoint: string, thresholdSeconds: int, warningSeconds: int}
     */
    private function activity(Request $request): array
    {
        $policy = $this->inactivityPolicy($request);
        $defaults = [
            'enabled' => false,
            'endpoint' => route(TimeTrackingPermissionCatalog::ACTIVITY_RECORD, absolute: false),
            'thresholdSeconds' => $policy->inactivityThresholdSeconds,
            'warningSeconds' => $policy->warningSeconds,
        ];
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $userId = $request->user()?->getAuthIdentifier();

        if (! is_string($userPublicId) || ! is_string($teamPublicId) || ! is_int($userId)) {
            return $defaults;
        }

        if (! $this->permissions->check(new EffectivePermissionRequest($userPublicId, TimeTrackingPermissionCatalog::ACTIVITY_RECORD, $teamPublicId))->allowed) {
            return $defaults;
        }

        $hasActiveWork = DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->exists();

        if (! $hasActiveWork) {
            return $defaults;
        }

        $hasActiveLock = DB::table(TimeTrackingDatabaseTable::BREAKS)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->exists()
            || DB::table(TimeTrackingDatabaseTable::OTHER_WORK)
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->exists();

        return [
            ...$defaults,
            'enabled' => ! $hasActiveLock,
        ];
    }

    private function inactivityPolicy(Request $request): InactivityPolicy
    {
        $userId = $request->user()?->getAuthIdentifier();
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (is_int($userId) && is_string($teamPublicId)) {
            $limits = $this->sessionLimits->limitsForUserId($userId, $teamPublicId);

            return new InactivityPolicy(max(60, $limits['inactivity'] * 60));
        }

        return new InactivityPolicy;
    }
}
