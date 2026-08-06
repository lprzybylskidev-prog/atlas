<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
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
        private EffectivePermissionChecker $permissions,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
        private ConnectionInterface $database,
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
        $selectedTeamPublicId = $this->selectedTeamPublicId($request, $teamOptions);
        $filterRequest = $this->requestForSectionFilters($request, $section, $selectedTeamPublicId);
        $assignments = $selectedTeamPublicId === ''
            ? []
            : $this->managerAssignments($filterRequest, $selectedTeamPublicId, $userPublicId, $permission);
        $selectedTeamId = $this->teamId($selectedTeamPublicId);

        $dailyDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_DAILY);
        $otherWorkDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_OTHER_WORK);
        $workSessionsDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_WORK_SESSIONS);
        $breaksDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_BREAKS);
        $correctionsDefinition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_CORRECTIONS);
        $dailyState = TableState::fromRequest($filterRequest, $dailyDefinition);
        $otherWorkState = TableState::fromPayload([], $otherWorkDefinition);
        $workSessionsState = TableState::fromPayload([], $workSessionsDefinition);
        $breaksState = TableState::fromPayload([], $breaksDefinition);
        $correctionsState = TableState::fromPayload([], $correctionsDefinition);

        $report = $this->reports->workTimeForAssignmentsRequest($filterRequest, $assignments, false);
        $dailyRows = $section === 'daily' ? $report->dailyRows : [];
        $otherWorkRows = $section === 'other_work' ? $report->otherWorkRows : [];
        $workSessionRows = $section === 'work_sessions' ? $this->reports->workSessionRowsForAssignments($filterRequest, $assignments) : [];
        $breakRows = $section === 'breaks' ? $this->reports->breakRowsForAssignments($filterRequest, $assignments) : [];
        $correctionRows = $section === 'corrections' ? $this->reports->correctionRowsForAssignments($filterRequest, $assignments) : [];

        $dailyResult = $this->tables->process($dailyRows, $dailyDefinition, $dailyState)
            ->withSavedViews($this->views->listFor($dailyDefinition->key, $userId, $selectedTeamId > 0 ? $selectedTeamId : $activeTeamId));
        $otherWorkResult = $this->tables->process($otherWorkRows, $otherWorkDefinition, $otherWorkState);
        $workSessionsResult = $this->tables->process($workSessionRows, $workSessionsDefinition, $workSessionsState);
        $breaksResult = $this->tables->process($breakRows, $breaksDefinition, $breaksState);
        $correctionsResult = $this->tables->process($correctionRows, $correctionsDefinition, $correctionsState);
        $dailyTable = $dailyResult->tableMeta($dailyDefinition->key);
        $dailyTable['state']['filters'] = $report->filters;

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
            'summary' => $report->summary,
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

        foreach ($this->database->table(TimeTrackingDatabaseTable::USER_TEAM_SETTINGS.' as settings')
            ->join(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments', 'settings.team_user_assignment_id', '=', 'assignments.id')
            ->join(TeamsDatabaseTable::TEAMS.' as teams', 'assignments.team_id', '=', 'teams.id')
            ->where('settings.tracking_enabled', true)
            ->distinct()
            ->orderBy('teams.name')
            ->get(['teams.id', 'teams.public_id', 'teams.name']) as $row) {
            $teamId = $this->intValue($row->id ?? null);
            $teamPublicId = $this->stringValue($row->public_id ?? null);

            if ($teamId > 0 && $teamPublicId !== '') {
                $teams[] = [
                    'id' => $teamId,
                    'publicId' => $teamPublicId,
                    'name' => $this->stringValue($row->name ?? null),
                ];
            }
        }

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

        return array_values($this->database->table(TimeTrackingDatabaseTable::USER_TEAM_SETTINGS.' as settings')
            ->join(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments', 'settings.team_user_assignment_id', '=', 'assignments.id')
            ->join(IdentityDatabaseTable::USERS.' as users', 'assignments.user_id', '=', 'users.id')
            ->where('settings.tracking_enabled', true)
            ->where('assignments.team_id', $teamId)
            ->whereIn('users.public_id', $visibleUserPublicIds)
            ->pluck('users.id')
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all());
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

        $selectedUserPublicId = $this->stringValue($request->query('user'));
        $query = $this->database->table(TimeTrackingDatabaseTable::USER_TEAM_SETTINGS.' as settings')
            ->join(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments', 'settings.team_user_assignment_id', '=', 'assignments.id')
            ->join(IdentityDatabaseTable::USERS.' as users', 'assignments.user_id', '=', 'users.id')
            ->join(TeamsDatabaseTable::TEAMS.' as teams', 'assignments.team_id', '=', 'teams.id')
            ->where('settings.tracking_enabled', true)
            ->where('assignments.team_id', $teamId)
            ->whereIn('users.public_id', $scope->visibleUserPublicIds);

        if ($selectedUserPublicId !== '') {
            $query->where('users.public_id', $selectedUserPublicId);
        }

        $assignments = [];

        foreach ($query->orderBy('users.name')->get([
            'users.id as user_id',
            'users.public_id as user_public_id',
            'users.name as user_name',
            'users.email as user_email',
            'teams.id as team_id',
            'teams.public_id as team_public_id',
            'teams.name as team_name',
        ]) as $row) {
            $assignments[] = [
                'userId' => $this->intValue($row->user_id ?? null),
                'userPublicId' => $this->stringValue($row->user_public_id ?? null),
                'userName' => $this->stringValue($row->user_name ?? null),
                'userEmail' => $this->stringValue($row->user_email ?? null),
                'teamId' => $this->intValue($row->team_id ?? null),
                'teamPublicId' => $this->stringValue($row->team_public_id ?? null),
                'teamName' => $this->stringValue($row->team_name ?? null),
            ];
        }

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

        foreach ($this->database->table(TimeTrackingDatabaseTable::OTHER_WORK_CATEGORIES.' as categories')
            ->join(TeamsDatabaseTable::TEAMS.' as teams', 'categories.scope_id', '=', 'teams.id')
            ->where('categories.scope_type', 'team')
            ->whereIn('teams.public_id', $teamPublicIds)
            ->orderBy('categories.label_pl')
            ->orderBy('categories.category_key')
            ->get(['categories.category_key', 'categories.label_pl', 'categories.label_en', 'teams.public_id as team_public_id']) as $row) {
            $categories[] = [
                'key' => $this->stringValue($row->category_key ?? null),
                'labelPl' => $this->stringValue($row->label_pl ?? null),
                'labelEn' => $this->stringValue($row->label_en ?? null),
                'teamPublicId' => $this->stringValue($row->team_public_id ?? null),
            ];
        }

        return array_values(array_filter($categories, static fn (array $category): bool => $category['key'] !== '' && $category['teamPublicId'] !== ''));
    }

    private function requestForSectionFilters(Request $request, string $section, string $teamPublicId): Request
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

        $id = $this->database->table(TeamsDatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->value('id');

        return is_numeric($id) ? (int) $id : 0;
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
