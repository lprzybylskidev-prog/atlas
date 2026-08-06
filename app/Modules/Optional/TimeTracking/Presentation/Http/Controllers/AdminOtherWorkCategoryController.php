<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminOtherWorkCategoryController
{
    public function __construct(
        private TimeTrackingModuleAccess $access,
        private UserTimeReportService $reports,
        private ManagerHierarchy $hierarchy,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureAllowed($request, $this->isManagerRoute($request) ? TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_INDEX : TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_CATEGORY_INDEX);

        $filters = $this->filters($request);
        $teamsByPublicId = $this->teamsByPublicId($request);
        $rows = $this->categoryRows($filters, $teamsByPublicId);

        return Inertia::render('TimeTracking/AdminOtherWorkCategories', [
            'surface' => $this->surface($request),
            'categories' => $rows,
            'teamOptions' => $this->teamOptions($request),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureAllowed($request, $this->isManagerRoute($request) ? TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_CREATE : TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_CATEGORY_CREATE);

        return Inertia::render('TimeTracking/AdminOtherWorkCategoryCreate', [
            'surface' => $this->surface($request),
            'teamOptions' => $this->teamOptions($request),
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

    /**
     * @return array{team: string, status: string}
     */
    private function filters(Request $request): array
    {
        $team = $request->query('team');
        $status = $request->query('status');

        return [
            'team' => is_string($team) ? $team : '',
            'status' => is_string($status) && in_array($status, ['active', 'inactive'], true) ? $status : 'all',
        ];
    }

    /**
     * @return array<string, array{id: int, name: string}>
     */
    private function teamsByPublicId(Request $request): array
    {
        $teams = [];

        foreach ($this->teamOptions($request) as $team) {
            $publicId = $this->stringValue($team['publicId']);
            $name = $this->stringValue($team['name']);
            $id = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $publicId)->value('id');

            if ($publicId !== '' && is_numeric($id)) {
                $teams[$publicId] = ['id' => (int) $id, 'name' => $name];
            }
        }

        return $teams;
    }

    /**
     * @return list<array{publicId: string, name: string, trackedUsers: int}>
     */
    private function teamOptions(Request $request): array
    {
        if (! $this->isManagerRoute($request)) {
            return $this->reports->adminTeamOptions();
        }

        $userPublicId = data_get($request->user(), 'public_id');

        if (! is_string($userPublicId)) {
            return [];
        }

        $teams = [];

        foreach ($this->reports->adminTeamOptions() as $team) {
            $teamPublicId = $this->stringValue($team['publicId']);

            if ($teamPublicId !== '' && $this->hierarchy->scopeFor($teamPublicId, $userPublicId)->visibleUserPublicIds !== []) {
                $teams[] = $team;
            }
        }

        return $teams;
    }

    /**
     * @param  array{team: string, status: string}  $filters
     * @param  array<string, array{id: int, name: string}>  $teamsByPublicId
     * @return list<array{publicId: string, teamPublicId: string, teamName: string, key: string, labelPl: string, labelEn: string, descriptionPl: string, descriptionEn: string, requiresComment: bool, autoApprovalEnabled: bool, isActive: bool}>
     */
    private function categoryRows(array $filters, array $teamsByPublicId): array
    {
        if ($teamsByPublicId === []) {
            return [];
        }

        $teamsById = [];
        foreach ($teamsByPublicId as $publicId => $team) {
            $teamsById[$team['id']] = ['publicId' => $publicId, 'name' => $team['name']];
        }

        $query = DB::table(TimeTrackingDatabaseTable::OTHER_WORK_CATEGORIES)
            ->where('scope_type', 'team')
            ->whereIn('scope_id', array_keys($teamsById));

        if ($filters['team'] !== '') {
            $team = $teamsByPublicId[$filters['team']] ?? null;
            $query->where('scope_id', $team['id'] ?? 0);
        }

        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        }

        if ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        $rows = [];

        foreach ($query->orderBy('scope_id')->orderBy('category_key')->get() as $row) {
            $teamId = $this->intValue($row->scope_id ?? null);
            $team = $teamsById[$teamId] ?? null;

            if ($team === null) {
                continue;
            }

            $rows[] = [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'teamPublicId' => $team['publicId'],
                'teamName' => $team['name'],
                'key' => $this->stringValue($row->category_key ?? null),
                'labelPl' => $this->stringValue($row->label_pl ?? null),
                'labelEn' => $this->stringValue($row->label_en ?? null),
                'descriptionPl' => $this->stringValue($row->description_pl ?? null),
                'descriptionEn' => $this->stringValue($row->description_en ?? null),
                'requiresComment' => (bool) ($row->requires_comment ?? false),
                'autoApprovalEnabled' => (bool) ($row->auto_approval_enabled ?? false),
                'isActive' => (bool) ($row->is_active ?? false),
            ];
        }

        return $rows;
    }

    private function activeTeamPublicId(Request $request): ?string
    {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($teamPublicId) ? $teamPublicId : null;
    }

    private function surface(Request $request): string
    {
        return $this->isManagerRoute($request) ? 'manager' : 'admin';
    }

    private function isManagerRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) && str_starts_with($routeName, 'manager.work-time.');
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
