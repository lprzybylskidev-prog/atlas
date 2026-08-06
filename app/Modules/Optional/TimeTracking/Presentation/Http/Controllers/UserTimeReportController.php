<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserTimeReportController
{
    public function __construct(
        private UserTimeReportService $reports,
        private TimeTrackingModuleAccess $access,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
        private UserTeamTrackingSettings $trackingSettings,
    ) {}

    public function __invoke(Request $request): Response
    {
        $dailyDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_USER_WORK_TIME_DAILY);
        $otherWorkDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_USER_OTHER_WORK);
        $workSessionsDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_USER_WORK_SESSIONS);
        $breaksDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_USER_BREAKS);
        $correctionsDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_USER_CORRECTIONS);
        $dailyState = TableState::fromRequest($request, $dailyDefinition);
        $otherWorkState = TableState::fromPayload([], $otherWorkDefinition);
        $workSessionsState = TableState::fromPayload([], $workSessionsDefinition);
        $breaksState = TableState::fromPayload([], $breaksDefinition);
        $correctionsState = TableState::fromPayload([], $correctionsDefinition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        $this->access->ensureAllowed(
            activeTeamId: $teamId,
            activeTeamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            userPublicId: is_string($userPublicId) ? $userPublicId : null,
            requiredPermission: TimeTrackingPermissionCatalog::USER_REPORT,
        );

        if ($userId <= 0 || $teamId <= 0 || ! $this->trackingSettings->isEnabledForUserTeam($userId, $teamId)) {
            abort(403);
        }

        $report = $this->reports->workTimeForRequest($request, $userId, $teamId);

        $dailyResult = $this->tables->process($report->dailyRows, $dailyDefinition, $dailyState)
            ->withSavedViews($this->views->listFor($dailyDefinition->key, $userId, $teamId));
        $dailyTable = $dailyResult->tableMeta($dailyDefinition->key);
        $dailyTable['state']['filters'] = $report->filters;

        $otherWorkResult = $this->tables->process($report->otherWorkRows, $otherWorkDefinition, $otherWorkState);
        $workSessionsResult = $this->tables->process($this->reports->userWorkSessionDetails($request, $userId, $teamId), $workSessionsDefinition, $workSessionsState);
        $breaksResult = $this->tables->process($this->reports->userBreakDetails($request, $userId, $teamId), $breaksDefinition, $breaksState);
        $correctionsResult = $this->tables->process($this->reports->userCorrectionDetails($request, $userId, $teamId), $correctionsDefinition, $correctionsState);

        return Inertia::render('TimeTracking/UserReport', [
            'dailyRows' => $dailyResult->rows,
            'otherWorkRows' => $otherWorkResult->rows,
            'workSessionRows' => $workSessionsResult->rows,
            'breakRows' => $breaksResult->rows,
            'correctionRows' => $correctionsResult->rows,
            'summary' => $report->summary,
            'comparison' => $report->comparison,
            'filters' => $report->filters,
            'dailyTable' => $dailyTable,
            'otherWorkTable' => $otherWorkResult->tableMeta($otherWorkDefinition->key),
            'workSessionsTable' => $workSessionsResult->tableMeta($workSessionsDefinition->key),
            'breaksTable' => $breaksResult->tableMeta($breaksDefinition->key),
            'correctionsTable' => $correctionsResult->tableMeta($correctionsDefinition->key),
        ]);
    }
}
