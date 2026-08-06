<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminTimeTrackingOperationsController
{
    public function __construct(
        private UserTimeReportService $reports,
        private TimeTrackingModuleAccess $access,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function daily(Request $request): Response
    {
        return $this->render($request, 'daily', TimeTrackingPermissionCatalog::ADMIN_SUMMARY);
    }

    public function otherWork(Request $request): Response
    {
        return $this->render($request, 'other_work', TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK);
    }

    public function workSessions(Request $request): Response
    {
        return $this->render($request, 'work_sessions', TimeTrackingPermissionCatalog::ADMIN_WORK_SESSIONS);
    }

    public function breaks(Request $request): Response
    {
        return $this->render($request, 'breaks', TimeTrackingPermissionCatalog::ADMIN_BREAKS);
    }

    public function corrections(Request $request): Response
    {
        return $this->render($request, 'corrections', TimeTrackingPermissionCatalog::ADMIN_CORRECTIONS);
    }

    private function render(Request $request, string $section, string $permission): Response
    {
        $dailyDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_DAILY);
        $otherWorkDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_OTHER_WORK);
        $workSessionsDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_WORK_SESSIONS);
        $breaksDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_BREAKS);
        $correctionsDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_CORRECTIONS);
        $dailyState = TableState::fromRequest($request, $dailyDefinition);
        $otherWorkState = TableState::fromPayload([], $otherWorkDefinition);
        $workSessionsState = TableState::fromPayload([], $workSessionsDefinition);
        $breaksState = TableState::fromPayload([], $breaksDefinition);
        $correctionsState = TableState::fromPayload([], $correctionsDefinition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $filterRequest = $this->requestForSectionFilters($request, $section);

        $this->access->ensureAllowed(
            activeTeamId: $teamId,
            activeTeamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            userPublicId: is_string($userPublicId) ? $userPublicId : null,
            requiredPermission: $permission,
        );

        $report = $this->reports->workTimeForAdminRequest($filterRequest);
        $workSessionRows = $this->reports->adminWorkSessionRows($filterRequest);
        $breakRows = $this->reports->adminBreakRows($filterRequest);
        $correctionRows = $this->reports->adminCorrectionRows($filterRequest);

        $dailyResult = $this->tables->process($report->dailyRows, $dailyDefinition, $dailyState)
            ->withSavedViews($this->views->listFor($dailyDefinition->key, $userId, $teamId));
        $otherWorkResult = $this->tables->process($report->otherWorkRows, $otherWorkDefinition, $otherWorkState);
        $workSessionsResult = $this->tables->process($workSessionRows, $workSessionsDefinition, $workSessionsState);
        $breaksResult = $this->tables->process($breakRows, $breaksDefinition, $breaksState);
        $correctionsResult = $this->tables->process($correctionRows, $correctionsDefinition, $correctionsState);
        $dailyTable = $dailyResult->tableMeta($dailyDefinition->key, AdminDataTableExportMeta::defaults());
        $dailyTable['state']['filters'] = $report->filters;

        return Inertia::render('TimeTracking/AdminOperations', [
            'section' => $section,
            'teamOptions' => $this->reports->adminTeamOptions(),
            'userOptions' => $this->reports->adminUserOptions($filterRequest),
            'userOptionsByTeam' => $this->reports->adminUserOptionsByTeam(),
            'moduleOptions' => $this->reports->adminModuleOptions($filterRequest),
            'moduleOptionsByTeam' => $this->reports->adminModuleOptionsByTeam(),
            'otherWorkCategoryOptions' => $this->reports->adminOtherWorkCategoryOptions($filterRequest),
            'otherWorkCategoryOptionsByTeam' => $this->reports->adminOtherWorkCategoryOptionsByTeam(),
            'dailyRows' => $dailyResult->rows,
            'otherWorkRows' => $otherWorkResult->rows,
            'workSessionRows' => $workSessionsResult->rows,
            'breakRows' => $breaksResult->rows,
            'correctionRows' => $correctionsResult->rows,
            'summary' => $report->summary,
            'filters' => $report->filters,
            'dailyTable' => $dailyTable,
            'otherWorkTable' => $otherWorkResult->tableMeta($otherWorkDefinition->key, AdminDataTableExportMeta::defaults()),
            'workSessionsTable' => $workSessionsResult->tableMeta($workSessionsDefinition->key, AdminDataTableExportMeta::defaults()),
            'breaksTable' => $breaksResult->tableMeta($breaksDefinition->key, AdminDataTableExportMeta::defaults()),
            'correctionsTable' => $correctionsResult->tableMeta($correctionsDefinition->key, AdminDataTableExportMeta::defaults()),
        ]);
    }

    private function requestForSectionFilters(Request $request, string $section): Request
    {
        $common = ['team', 'user', 'range', 'from', 'to'];
        $sectionKeys = match ($section) {
            'daily' => [...$common, 'compare'],
            'other_work' => [...$common, 'category', 'status', 'decision_state', 'closure_reason', 'review'],
            'breaks' => [...$common, 'status', 'closure_reason', 'review'],
            'corrections' => [...$common, 'correction_type', 'status', 'review'],
            'work_sessions' => [...$common, 'status', 'closure_reason'],
            default => $common,
        };

        return $request->duplicate(
            query: array_intersect_key($request->query(), array_flip($sectionKeys)),
        );
    }
}
