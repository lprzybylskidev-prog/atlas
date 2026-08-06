<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Inertia;

use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\Contracts\TeamLookup;
use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final readonly class TimeTrackingRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function __construct(
        private ManagerHierarchy $hierarchy,
        private TeamLookup $teams,
        private UserTeamTrackingSettings $trackingSettings,
    ) {}

    public function key(): string
    {
        return 'optional.time-tracking.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return [
            TimeTrackingPermissionCatalog::ADMIN_SUMMARY,
            TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK,
            TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_CATEGORY_INDEX,
            TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_CATEGORY_CREATE,
            TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_DECIDE,
            TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_FORCE_CLOSE,
            TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_SHOW,
            TimeTrackingPermissionCatalog::ADMIN_WORK_SESSIONS,
            TimeTrackingPermissionCatalog::ADMIN_WORK_SESSION_SHOW,
            TimeTrackingPermissionCatalog::ADMIN_TERMINATE_SESSION,
            TimeTrackingPermissionCatalog::ADMIN_BREAKS,
            TimeTrackingPermissionCatalog::ADMIN_BREAK_FORCE_CLOSE,
            TimeTrackingPermissionCatalog::ADMIN_BREAK_SHOW,
            TimeTrackingPermissionCatalog::ADMIN_BREAK_CONVERT_EXCESS,
            TimeTrackingPermissionCatalog::ADMIN_CORRECTIONS,
            TimeTrackingPermissionCatalog::ADMIN_CORRECTION_DECIDE,
            TimeTrackingPermissionCatalog::ADMIN_MANUAL_ENTRY,
            TimeTrackingPermissionCatalog::ADMIN_MANUAL_ENTRY_STORE,
            TimeTrackingPermissionCatalog::ADMIN_CORRECTION_SHOW,
        ];
    }

    public function applicationRoutes(Request $request): array
    {
        $routes = [];
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
            return [];
        }

        if ($this->activeUserTeamTrackingEnabled($request, $teamPublicId)) {
            $routes = [
                TimeTrackingPermissionCatalog::USER_REPORT,
                TimeTrackingPermissionCatalog::USER_CORRECTION_REQUEST_STORE,
                TimeTrackingPermissionCatalog::BREAK_START,
                TimeTrackingPermissionCatalog::OTHER_WORK_CREATE,
                TimeTrackingPermissionCatalog::OTHER_WORK_START,
            ];
        }

        if ($this->hierarchy->scopeFor($teamPublicId, $userPublicId)->visibleUserPublicIds !== []) {
            $routes = [
                ...$routes,
                TimeTrackingPermissionCatalog::MANAGER_PANEL,
                TimeTrackingPermissionCatalog::MANAGER_REPORT,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_SUMMARY,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_OTHER_WORK,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSIONS,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_BREAKS,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_CORRECTIONS,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSION_SHOW,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_BREAK_SHOW,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_OTHER_WORK_SHOW,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_CORRECTION_SHOW,
                TimeTrackingPermissionCatalog::MANAGER_TERMINATE_SESSION,
                TimeTrackingPermissionCatalog::MANAGER_BREAK_FORCE_CLOSE,
                TimeTrackingPermissionCatalog::MANAGER_BREAK_CONVERT_EXCESS,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_FORCE_CLOSE,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_DECIDE,
                TimeTrackingPermissionCatalog::MANAGER_CORRECTION_DECIDE,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_INDEX,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_CREATE,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_STORE,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_DEACTIVATE,
            ];
        }

        return $routes;
    }

    private function activeUserTeamTrackingEnabled(Request $request, string $teamPublicId): bool
    {
        $userId = $request->user()?->getAuthIdentifier();
        $teamId = $this->teams->internalIdForPublicId($teamPublicId);

        if (! is_int($userId) || $teamId === null) {
            return false;
        }

        return $this->trackingSettings->isEnabledForUserTeam($userId, $teamId);
    }
}
