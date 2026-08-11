<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\RegisteredTables;
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
        private TableRequestContext $context,
        private UserTeamTrackingSettings $trackingSettings,
        private TableSavedViewService $views,
    ) {}

    public function __invoke(Request $request): Response
    {
        $section = $this->section($request);
        $dailyDefinition = RegisteredTables::get(RegisteredTables::TIME_TRACKING_USER_WORK_TIME_DAILY);
        $otherWorkDefinition = RegisteredTables::get(RegisteredTables::TIME_TRACKING_USER_OTHER_WORK);
        $workSessionsDefinition = RegisteredTables::get(RegisteredTables::TIME_TRACKING_USER_WORK_SESSIONS);
        $breaksDefinition = RegisteredTables::get(RegisteredTables::TIME_TRACKING_USER_BREAKS);
        $correctionsDefinition = RegisteredTables::get(RegisteredTables::TIME_TRACKING_USER_CORRECTIONS);
        $dailyState = TableState::fromRequest($request, $dailyDefinition);
        $otherWorkState = TableState::fromRequest($request, $otherWorkDefinition);
        $workSessionsState = TableState::fromRequest($request, $workSessionsDefinition);
        $breaksState = TableState::fromRequest($request, $breaksDefinition);
        $correctionsState = TableState::fromRequest($request, $correctionsDefinition);
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

        $dailyResult = $this->tables->process($section === 'daily' ? $report->dailyRows : [], $dailyDefinition, $dailyState)
            ->withSavedViews($this->views->listFor($dailyDefinition->key, $userId, $teamId));
        $dailyTable = $dailyResult->tableMeta($dailyDefinition->key);
        $dailyTable['state']['filters'] = $report->filters;

        $otherWorkResult = $this->tables->process($section === 'other_work' ? $report->otherWorkRows : [], $otherWorkDefinition, $otherWorkState)
            ->withSavedViews($this->views->listFor($otherWorkDefinition->key, $userId, $teamId));
        $workSessionsResult = $this->tables->process($section === 'work_sessions' ? $this->reports->userWorkSessionDetails($request, $userId, $teamId) : [], $workSessionsDefinition, $workSessionsState)
            ->withSavedViews($this->views->listFor($workSessionsDefinition->key, $userId, $teamId));
        $breaksResult = $this->tables->process($section === 'breaks' ? $this->reports->userBreakDetails($request, $userId, $teamId) : [], $breaksDefinition, $breaksState)
            ->withSavedViews($this->views->listFor($breaksDefinition->key, $userId, $teamId));
        $correctionsResult = $this->tables->process($section === 'corrections' ? $this->reports->userCorrectionDetails($request, $userId, $teamId) : [], $correctionsDefinition, $correctionsState)
            ->withSavedViews($this->views->listFor($correctionsDefinition->key, $userId, $teamId));
        $summaryRows = match ($section) {
            'daily' => $dailyResult->filteredRows,
            'other_work' => $otherWorkResult->filteredRows,
            'work_sessions' => $workSessionsResult->filteredRows,
            'breaks' => $breaksResult->filteredRows,
            'corrections' => $correctionsResult->filteredRows,
            default => [],
        };

        return Inertia::render('TimeTracking/UserReport', [
            'section' => $section,
            'dailyRows' => $dailyResult->rows,
            'otherWorkRows' => $otherWorkResult->rows,
            'workSessionRows' => $workSessionsResult->rows,
            'breakRows' => $breaksResult->rows,
            'correctionRows' => $correctionsResult->rows,
            'summary' => $this->reports->summaryForOperationRows($section, $summaryRows),
            'comparison' => $report->comparison,
            'filters' => $report->filters,
            'dailyTable' => $dailyTable,
            'otherWorkTable' => $otherWorkResult->tableMeta($otherWorkDefinition->key),
            'workSessionsTable' => $workSessionsResult->tableMeta($workSessionsDefinition->key),
            'breaksTable' => $breaksResult->tableMeta($breaksDefinition->key),
            'correctionsTable' => $correctionsResult->tableMeta($correctionsDefinition->key),
        ]);
    }

    private function section(Request $request): string
    {
        $section = $request->query('section');

        return is_string($section) && in_array($section, ['daily', 'work_sessions', 'breaks', 'other_work', 'corrections'], true)
            ? $section
            : 'daily';
    }
}
