<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminManualEntryController
{
    public function __construct(
        private TimeTrackingModuleAccess $access,
        private UserTimeReportService $reports,
    ) {}

    public function create(Request $request): Response
    {
        $this->ensureAllowed($request, TimeTrackingPermissionCatalog::ADMIN_MANUAL_ENTRY);

        return Inertia::render('TimeTracking/AdminManualEntryCreate', [
            'teamOptions' => $this->reports->adminTeamOptions(),
            'userOptionsByTeam' => $this->reports->adminUserOptionsByTeam(),
            'otherWorkCategoryOptionsByTeam' => $this->reports->adminOtherWorkCategoryOptionsByTeam(),
            'defaultTeamPublicId' => $this->activeTeamPublicId($request),
        ]);
    }

    private function ensureAllowed(Request $request, string $permission): void
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $this->activeTeamPublicId($request);
        $teamId = is_string($teamPublicId)
            ? DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id')
            : null;

        $this->access->ensureAllowed(
            activeTeamId: is_numeric($teamId) ? (int) $teamId : null,
            activeTeamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            userPublicId: is_string($userPublicId) ? $userPublicId : null,
            requiredPermission: $permission,
        );
    }

    private function activeTeamPublicId(Request $request): ?string
    {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($teamPublicId) && $teamPublicId !== '' ? $teamPublicId : null;
    }
}
