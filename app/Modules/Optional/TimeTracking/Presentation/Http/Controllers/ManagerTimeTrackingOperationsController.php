<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\TableNames\TimeTrackingDatabaseTable;
use App\Shared\Application\Authorization\Contracts\EffectivePermissionChecker;
use App\Shared\Application\Authorization\DTOs\EffectivePermissionRequest;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\RegisteredTables;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ManagerTimeTrackingOperationsController
{
    public function __construct(
        private UserTimeReportService $reports,
        private TimeTrackingModuleAccess $access,
        private ManagerHierarchy $hierarchy,
        private TeamLookup $teams,
        private EffectivePermissionChecker $permissions,
        private ArrayTableProcessor $tables,
        private TableRequestContext $context,
        private ConnectionInterface $database,
        private TableSavedViewService $views,
    ) {}

    public function daily(Request $request): Response
    {
        return $this->render($request, 'daily', TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_SUMMARY);
    }

    public function otherWork(Request $request): Response
    {
        return $this->render($request, 'other_work', TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_OTHER_WORK);
    }

    public function workSessions(Request $request): Response
    {
        return $this->render($request, 'work_sessions', TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSIONS);
    }

    public function breaks(Request $request): Response
    {
        return $this->render($request, 'breaks', TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_BREAKS);
    }

    public function corrections(Request $request): Response
    {
        return $this->render($request, 'corrections', TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_CORRECTIONS);
    }

    private function render(Request $request, string $section, string $permission): Response
    {
        [$userId, $activeTeamId] = $this->context->userTeam($request);
        $userPublicId = $this->currentUserPublicId($request);

        if ($userPublicId === null || $userId <= 0 || $activeTeamId <= 0) {
            abort(403);
        }

        $teamOptions = $this->managerTeamOptions($userPublicId, $permission);

        if ($teamOptions === []) {
            abort(403);
        }

        $selectedTeamPublicId = $this->selectedTeamPublicId($request, $teamOptions);
        $filterRequest = $this->requestForSectionFilters($request, $section, $selectedTeamPublicId);
        $assignments = $selectedTeamPublicId === ''
            ? []
            : $this->managerAssignments($filterRequest, $selectedTeamPublicId, $userPublicId, $permission);
        $selectedTeamId = $this->teamId($selectedTeamPublicId);

        $dailyDefinition = RegisteredTables::get(RegisteredTables::TIME_TRACKING_MANAGER_OPERATIONS_DAILY);
        $otherWorkDefinition = RegisteredTables::get(RegisteredTables::TIME_TRACKING_MANAGER_OPERATIONS_OTHER_WORK);
        $workSessionsDefinition = RegisteredTables::get(RegisteredTables::TIME_TRACKING_MANAGER_OPERATIONS_WORK_SESSIONS);
        $breaksDefinition = RegisteredTables::get(RegisteredTables::TIME_TRACKING_MANAGER_OPERATIONS_BREAKS);
        $correctionsDefinition = RegisteredTables::get(RegisteredTables::TIME_TRACKING_MANAGER_OPERATIONS_CORRECTIONS);
        $dailyState = TableState::fromRequest($filterRequest, $dailyDefinition);
        $otherWorkState = TableState::fromRequest($filterRequest, $otherWorkDefinition);
        $workSessionsState = TableState::fromRequest($filterRequest, $workSessionsDefinition);
        $breaksState = TableState::fromRequest($filterRequest, $breaksDefinition);
        $correctionsState = TableState::fromRequest($filterRequest, $correctionsDefinition);

        $report = $this->reports->workTimeForAssignmentsRequest($filterRequest, $assignments);
        $dailyRows = $section === 'daily' ? $report->dailyRows : [];
        $otherWorkRows = $section === 'other_work' ? $report->otherWorkRows : [];
        $workSessionRows = $section === 'work_sessions' ? $this->reports->workSessionRowsForAssignments($filterRequest, $assignments) : [];
        $breakRows = $section === 'breaks' ? $this->reports->breakRowsForAssignments($filterRequest, $assignments) : [];
        $correctionRows = $section === 'corrections' ? $this->reports->correctionRowsForAssignments($filterRequest, $assignments) : [];

        $dailyResult = $this->tables->process($dailyRows, $dailyDefinition, $dailyState)
            ->withSavedViews($this->views->listFor($dailyDefinition->key, $userId, $activeTeamId));
        $otherWorkResult = $this->tables->process($otherWorkRows, $otherWorkDefinition, $otherWorkState)
            ->withSavedViews($this->views->listFor($otherWorkDefinition->key, $userId, $activeTeamId));
        $workSessionsResult = $this->tables->process($workSessionRows, $workSessionsDefinition, $workSessionsState)
            ->withSavedViews($this->views->listFor($workSessionsDefinition->key, $userId, $activeTeamId));
        $breaksResult = $this->tables->process($breakRows, $breaksDefinition, $breaksState)
            ->withSavedViews($this->views->listFor($breaksDefinition->key, $userId, $activeTeamId));
        $correctionsResult = $this->tables->process($correctionRows, $correctionsDefinition, $correctionsState)
            ->withSavedViews($this->views->listFor($correctionsDefinition->key, $userId, $activeTeamId));
        $dailyTable = $dailyResult->tableMeta($dailyDefinition->key);
        $dailyTable['state']['filters'] = $report->filters;
        $summaryRows = match ($section) {
            'daily' => $dailyResult->filteredRows,
            'other_work' => $otherWorkResult->filteredRows,
            'work_sessions' => $workSessionsResult->filteredRows,
            'breaks' => $breaksResult->filteredRows,
            'corrections' => $correctionsResult->filteredRows,
            default => [],
        };

        return Inertia::render('TimeTracking/AdminOperations', [
            'surface' => 'manager',
            'section' => $section,
            'teamOptions' => $teamOptions,
            'userOptions' => $this->userOptions($assignments),
            'userOptionsByTeam' => $this->userOptionsByTeam($userPublicId, $permission),
            'moduleOptions' => ['System'],
            'moduleOptionsByTeam' => $this->moduleOptionsByTeam($teamOptions),
            'otherWorkCategoryOptions' => $this->categoryOptions($teamOptions, $selectedTeamPublicId),
            'otherWorkCategoryOptionsByTeam' => $this->categoryOptionsByTeam($teamOptions),
            'dailyRows' => $dailyResult->rows,
            'otherWorkRows' => $otherWorkResult->rows,
            'workSessionRows' => $workSessionsResult->rows,
            'breakRows' => $breaksResult->rows,
            'correctionRows' => $correctionsResult->rows,
            'summary' => $this->reports->summaryForOperationRows($section, $summaryRows),
            'filters' => $report->filters,
            'dailyTable' => $dailyTable,
            'otherWorkTable' => $otherWorkResult->tableMeta($otherWorkDefinition->key),
            'workSessionsTable' => $workSessionsResult->tableMeta($workSessionsDefinition->key),
            'breaksTable' => $breaksResult->tableMeta($breaksDefinition->key),
            'correctionsTable' => $correctionsResult->tableMeta($correctionsDefinition->key),
        ]);
    }

    /**
     * @param  list<array{publicId: string, name: string, trackedUsers: int}>  $teamOptions
     */
    private function selectedTeamPublicId(Request $request, array $teamOptions): string
    {
        $available = array_column($teamOptions, 'publicId');
        $requested = $this->stringValue($request->query('team'));

        if ($requested !== '' && in_array($requested, $available, true)) {
            return $requested;
        }

        $active = $request->hasSession() ? $this->stringValue($request->session()->get('active_team_public_id')) : '';

        return in_array($active, $available, true) ? $active : '';
    }

    /**
     * @return list<array{publicId: string, name: string, trackedUsers: int}>
     */
    private function managerTeamOptions(string $managerUserPublicId, string $permission): array
    {
        $teams = [];

        foreach ($this->trackedTeams() as $team) {
            $teamPublicId = $team['publicId'];
            $teamId = $team['id'];
            $scope = $this->hierarchy->scopeFor($teamPublicId, $managerUserPublicId);

            if ($scope->visibleUserPublicIds === []
                || ! $this->permissions->check(new EffectivePermissionRequest($managerUserPublicId, $permission, $teamPublicId))->allowed
                || ! $this->access->allows($teamId, $teamPublicId, $managerUserPublicId, $permission)
            ) {
                continue;
            }

            $trackedUsers = count($this->trackedVisibleUserIds($teamId, $scope->visibleUserPublicIds));

            if ($trackedUsers > 0) {
                $teams[] = [
                    'publicId' => $teamPublicId,
                    'name' => $team['name'],
                    'trackedUsers' => $trackedUsers,
                ];
            }
        }

        usort($teams, fn (array $first, array $second): int => strcmp($first['name'], $second['name']));

        return $teams;
    }

    /**
     * @return list<array{id: int, publicId: string, name: string}>
     */
    private function trackedTeams(): array
    {
        $teams = [];

        foreach ($this->trackedAssignments() as $assignment) {
            $teams[$assignment['teamId']] = [
                'id' => $assignment['teamId'],
                'publicId' => $assignment['teamPublicId'],
                'name' => $assignment['teamName'],
            ];
        }

        $teams = array_values($teams);
        usort($teams, fn (array $first, array $second): int => strcmp($first['name'], $second['name']));

        return $teams;
    }

    /**
     * @param  list<string>  $visibleUserPublicIds
     * @return list<int>
     */
    private function trackedVisibleUserIds(int $teamId, array $visibleUserPublicIds): array
    {
        if ($visibleUserPublicIds === []) {
            return [];
        }

        return array_values(array_map(
            static fn (array $assignment): int => $assignment['userId'],
            array_filter(
                $this->trackedAssignments($teamId),
                static fn (array $assignment): bool => in_array($assignment['userPublicId'], $visibleUserPublicIds, true),
            ),
        ));
    }

    /**
     * @return list<array{userId: int, userPublicId: string, userName: string, userEmail: string, teamId: int, teamPublicId: string, teamName: string}>
     */
    private function managerAssignments(Request $request, string $teamPublicId, string $managerUserPublicId, string $permission): array
    {
        $teamId = $this->teamId($teamPublicId);

        if ($teamId < 1
            || ! $this->permissions->check(new EffectivePermissionRequest($managerUserPublicId, $permission, $teamPublicId))->allowed
            || ! $this->access->allows($teamId, $teamPublicId, $managerUserPublicId, $permission)
        ) {
            abort(403);
        }

        $scope = $this->hierarchy->scopeFor($teamPublicId, $managerUserPublicId);

        if ($scope->visibleUserPublicIds === []) {
            abort(403);
        }

        $assignments = [];
        $selectedUserPublicId = $this->stringValue($request->query('user'));

        foreach ($this->trackedAssignments($teamId) as $assignment) {
            if (! in_array($assignment['userPublicId'], $scope->visibleUserPublicIds, true)
                || ($selectedUserPublicId !== '' && $assignment['userPublicId'] !== $selectedUserPublicId)
            ) {
                continue;
            }

            $assignments[] = [
                'userId' => $assignment['userId'],
                'userPublicId' => $assignment['userPublicId'],
                'userName' => $assignment['userName'],
                'userEmail' => $assignment['userEmail'],
                'teamId' => $assignment['teamId'],
                'teamPublicId' => $assignment['teamPublicId'],
                'teamName' => $assignment['teamName'],
            ];
        }

        usort($assignments, fn (array $first, array $second): int => strcmp($first['userName'], $second['userName']));

        return array_values(array_filter($assignments, static fn (array $assignment): bool => $assignment['userId'] > 0 && $assignment['teamId'] > 0));
    }

    /**
     * @param  list<array{userId: int, userPublicId: string, userName: string, userEmail: string, teamId: int, teamPublicId: string, teamName: string}>  $assignments
     * @return list<array{publicId: string, name: string, email: string}>
     */
    private function userOptions(array $assignments): array
    {
        $users = [];

        foreach ($assignments as $assignment) {
            $users[$assignment['userPublicId']] = [
                'publicId' => $assignment['userPublicId'],
                'name' => $assignment['userName'],
                'email' => $assignment['userEmail'],
            ];
        }

        $users = array_values($users);
        usort($users, fn (array $first, array $second): int => strcmp($first['name'], $second['name']));

        return $users;
    }

    /**
     * @return array<string, list<array{publicId: string, name: string, email: string}>>
     */
    private function userOptionsByTeam(string $managerUserPublicId, string $permission): array
    {
        $usersByTeam = [];

        foreach ($this->managerTeamOptions($managerUserPublicId, $permission) as $team) {
            $request = Request::create('/manager/work-time/options', 'GET', ['team' => $team['publicId']]);
            $usersByTeam[$team['publicId']] = $this->userOptions($this->managerAssignments($request, $team['publicId'], $managerUserPublicId, $permission));
        }

        ksort($usersByTeam);

        return $usersByTeam;
    }

    /**
     * @param  list<array{publicId: string, name: string, trackedUsers: int}>  $teamOptions
     * @return array<string, list<string>>
     */
    private function moduleOptionsByTeam(array $teamOptions): array
    {
        $options = [];

        foreach ($teamOptions as $team) {
            $options[$team['publicId']] = ['System'];
        }

        return $options;
    }

    /**
     * @param  list<array{publicId: string, name: string, trackedUsers: int}>  $teamOptions
     * @return list<array{key: string, labelPl: string, labelEn: string, teamPublicId: string}>
     */
    private function categoryOptions(array $teamOptions, string $selectedTeamPublicId): array
    {
        if ($selectedTeamPublicId !== '') {
            return $this->categoryOptionsForTeamPublicIds([$selectedTeamPublicId]);
        }

        return $this->categoryOptionsForTeamPublicIds(array_column($teamOptions, 'publicId'));
    }

    /**
     * @param  list<array{publicId: string, name: string, trackedUsers: int}>  $teamOptions
     * @return array<string, list<array{key: string, labelPl: string, labelEn: string, teamPublicId: string}>>
     */
    private function categoryOptionsByTeam(array $teamOptions): array
    {
        $categories = [];

        foreach ($teamOptions as $team) {
            $categories[$team['publicId']] = $this->categoryOptionsForTeamPublicIds([$team['publicId']]);
        }

        ksort($categories);

        return $categories;
    }

    /**
     * @param  list<string>  $teamPublicIds
     * @return list<array{key: string, labelPl: string, labelEn: string, teamPublicId: string}>
     */
    private function categoryOptionsForTeamPublicIds(array $teamPublicIds): array
    {
        if ($teamPublicIds === []) {
            return [];
        }

        $categories = [];

        $teamIds = $this->teams->internalIdsForPublicIds($teamPublicIds);
        $teamPublicIdsById = [];

        foreach ($this->teams->summariesForInternalIds($teamIds) as $team) {
            $teamPublicIdsById[$team->internalId] = $team->publicId;
        }

        foreach ($this->database->table(TimeTrackingDatabaseTable::OTHER_WORK_CATEGORIES.' as categories')
            ->where('categories.scope_type', 'team')
            ->whereIn('categories.scope_id', $teamIds)
            ->orderBy('categories.label_pl')
            ->orderBy('categories.category_key')
            ->get(['categories.category_key', 'categories.label_pl', 'categories.label_en', 'categories.scope_id']) as $row) {
            $teamId = $this->intValue($row->scope_id ?? null);

            $categories[] = [
                'key' => $this->stringValue($row->category_key ?? null),
                'labelPl' => $this->stringValue($row->label_pl ?? null),
                'labelEn' => $this->stringValue($row->label_en ?? null),
                'teamPublicId' => $teamPublicIdsById[$teamId] ?? '',
            ];
        }

        return array_values(array_filter($categories, static fn (array $category): bool => $category['key'] !== '' && $category['teamPublicId'] !== ''));
    }

    private function requestForSectionFilters(Request $request, string $section, string $teamPublicId): Request
    {
        $common = ['team', 'user', 'range', 'from', 'to', 'page', 'per_page', 'sort', 'direction', 'search', 'columns', 'column_order', 'view'];
        $sectionKeys = match ($section) {
            'daily' => [...$common, 'compare'],
            'other_work' => [...$common, 'category', 'status', 'decision_state', 'closure_reason', 'review'],
            'breaks' => [...$common, 'status', 'closure_reason', 'review'],
            'corrections' => [...$common, 'correction_type', 'status', 'review'],
            'work_sessions' => [...$common, 'status', 'closure_reason'],
            default => $common,
        };
        $query = array_intersect_key($request->query(), array_flip($sectionKeys));

        if ($teamPublicId !== '') {
            $query['team'] = $teamPublicId;
        }

        return $request->duplicate(query: $query);
    }

    private function teamId(string $teamPublicId): int
    {
        if ($teamPublicId === '') {
            return 0;
        }

        return $this->teams->internalIdForPublicId($teamPublicId) ?? 0;
    }

    /**
     * @return list<array{userId: int, userPublicId: string, userName: string, userEmail: string, teamId: int, teamPublicId: string, teamName: string}>
     */
    private function trackedAssignments(?int $teamId = null): array
    {
        $rows = [];

        foreach ($this->teams->assignmentSummariesForInternalIds($this->trackedAssignmentIds()) as $assignment) {
            if ($teamId !== null && $assignment->teamId !== $teamId) {
                continue;
            }

            $rows[] = [
                'userId' => $assignment->userId,
                'userPublicId' => $assignment->userPublicId,
                'userName' => $assignment->userName,
                'userEmail' => $assignment->userEmail,
                'teamId' => $assignment->teamId,
                'teamPublicId' => $assignment->teamPublicId,
                'teamName' => $assignment->teamName,
            ];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function trackedAssignmentIds(): array
    {
        return array_values($this->database->table(TimeTrackingDatabaseTable::USER_TEAM_SETTINGS)
            ->where('tracking_enabled', true)
            ->pluck('team_user_assignment_id')
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all());
    }

    private function currentUserPublicId(Request $request): ?string
    {
        $userPublicId = data_get($request->user(), 'public_id');

        return is_string($userPublicId) && $userPublicId !== '' ? $userPublicId : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
