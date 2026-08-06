<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ManagerTimeReportController
{
    public function __construct(
        private UserTimeReportService $reports,
        private TimeTrackingModuleAccess $access,
        private ManagerHierarchy $hierarchy,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
        private ConnectionInterface $database,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::TIME_TRACKING_MANAGER_REPORT);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $userPublicId = is_string($userPublicId) ? $userPublicId : null;
        $teamPublicId = is_string($teamPublicId) ? $teamPublicId : null;

        $this->access->ensureAllowed(
            activeTeamId: $teamId,
            activeTeamPublicId: $teamPublicId,
            userPublicId: $userPublicId,
        );

        if ($teamPublicId === null || $userPublicId === null || $userId <= 0 || $teamId <= 0) {
            abort(403);
        }

        $scope = $this->hierarchy->scopeFor($teamPublicId, $userPublicId);

        if ($scope->visibleUserPublicIds === []) {
            abort(403);
        }

        $report = $this->reports->forManagerRequest($request, $teamId, $scope->visibleUserPublicIds);

        $result = $this->tables->process($report->rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults(route('exports.data-table')));
        $table['state']['filters'] = $report->filters;

        return Inertia::render('TimeTracking/ManagerReport', [
            'rows' => $result->rows,
            'summary' => $report->summary,
            'comparison' => $report->comparison,
            'filters' => $report->filters,
            'filterOptions' => [
                'modules' => $this->modules($report->rows),
            ],
            'scope' => [
                'headManager' => $scope->headManager,
                'visibleUsers' => count($scope->visibleUserPublicIds),
            ],
            'teamSummary' => $this->teamSummary($teamId, $scope->visibleUserPublicIds),
            'statusFeed' => $this->statusFeed($teamId, $scope->visibleUserPublicIds),
            'table' => $table,
        ]);
    }

    /**
     * @param  list<string>  $visibleUserPublicIds
     * @return list<array{publicId: string, userName: string, userEmail: string, type: string, status: string, occurredAt: string, context: string}>
     */
    private function statusFeed(int $teamId, array $visibleUserPublicIds): array
    {
        $users = $this->visibleUsers($visibleUserPublicIds);
        $userIds = array_keys($users);

        if ($userIds === []) {
            return [];
        }

        $events = [
            ...$this->workStatusEvents($teamId, $userIds, $users),
            ...$this->breakStatusEvents($teamId, $userIds, $users),
            ...$this->otherWorkStatusEvents($teamId, $userIds, $users),
            ...$this->correctionStatusEvents($teamId, $userIds, $users),
        ];

        usort($events, fn (array $first, array $second): int => strcmp($second['occurredAt'], $first['occurredAt']));

        return array_slice($events, 0, 12);
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<int, array{name: string, email: string}>  $users
     * @return list<array{publicId: string, userName: string, userEmail: string, type: string, status: string, occurredAt: string, context: string}>
     */
    private function workStatusEvents(int $teamId, array $userIds, array $users): array
    {
        $events = [];

        foreach ($this->database->table(TimeTrackingDatabaseTable::WORK_SESSIONS)
            ->where('team_id', $teamId)
            ->whereIn('user_id', $userIds)
            ->orderByDesc('started_at')
            ->limit(24)
            ->get(['public_id', 'user_id', 'started_at', 'ended_at', 'closure_reason']) as $row) {
            $events[] = $this->statusFeedItem($row, $users, 'work', 'started', $this->stringValue($row->started_at ?? null), 'System');

            if ($this->stringValue($row->ended_at ?? null) !== '') {
                $events[] = $this->statusFeedItem($row, $users, 'work', $this->stringValue($row->closure_reason ?? null) === '' ? 'ended' : $this->stringValue($row->closure_reason ?? null), $this->stringValue($row->ended_at ?? null), 'System');
            }
        }

        return $events;
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<int, array{name: string, email: string}>  $users
     * @return list<array{publicId: string, userName: string, userEmail: string, type: string, status: string, occurredAt: string, context: string}>
     */
    private function breakStatusEvents(int $teamId, array $userIds, array $users): array
    {
        $events = [];

        foreach ($this->database->table(TimeTrackingDatabaseTable::BREAKS)
            ->where('team_id', $teamId)
            ->whereIn('user_id', $userIds)
            ->orderByDesc('started_at')
            ->limit(24)
            ->get(['public_id', 'user_id', 'started_at', 'ended_at', 'closure_reason', 'requires_manager_review']) as $row) {
            $events[] = $this->statusFeedItem($row, $users, 'break', 'started', $this->stringValue($row->started_at ?? null), 'Break');

            if ($this->stringValue($row->ended_at ?? null) !== '') {
                $status = (bool) ($row->requires_manager_review ?? false) ? 'under_review' : ($this->stringValue($row->closure_reason ?? null) === '' ? 'ended' : $this->stringValue($row->closure_reason ?? null));
                $events[] = $this->statusFeedItem($row, $users, 'break', $status, $this->stringValue($row->ended_at ?? null), 'Break');
            }
        }

        return $events;
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<int, array{name: string, email: string}>  $users
     * @return list<array{publicId: string, userName: string, userEmail: string, type: string, status: string, occurredAt: string, context: string}>
     */
    private function otherWorkStatusEvents(int $teamId, array $userIds, array $users): array
    {
        $events = [];

        foreach ($this->database->table(TimeTrackingDatabaseTable::OTHER_WORK)
            ->where('team_id', $teamId)
            ->whereIn('user_id', $userIds)
            ->orderByDesc('started_at')
            ->limit(24)
            ->get(['public_id', 'user_id', 'category_key', 'approval_status', 'started_at', 'ended_at']) as $row) {
            $context = $this->stringValue($row->category_key ?? null);
            $events[] = $this->statusFeedItem($row, $users, 'other_work', $this->stringValue($row->approval_status ?? null), $this->stringValue($row->started_at ?? null), $context);

            if ($this->stringValue($row->ended_at ?? null) !== '') {
                $events[] = $this->statusFeedItem($row, $users, 'other_work', 'ended', $this->stringValue($row->ended_at ?? null), $context);
            }
        }

        return $events;
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<int, array{name: string, email: string}>  $users
     * @return list<array{publicId: string, userName: string, userEmail: string, type: string, status: string, occurredAt: string, context: string}>
     */
    private function correctionStatusEvents(int $teamId, array $userIds, array $users): array
    {
        $events = [];

        foreach ($this->database->table(TimeTrackingDatabaseTable::CORRECTION_REQUESTS)
            ->where('team_id', $teamId)
            ->whereIn('user_id', $userIds)
            ->orderByDesc('requested_at')
            ->limit(24)
            ->get(['public_id', 'user_id', 'request_type', 'status', 'requested_at', 'decided_at']) as $row) {
            $events[] = $this->statusFeedItem($row, $users, 'correction', 'pending', $this->stringValue($row->requested_at ?? null), $this->stringValue($row->request_type ?? null));

            if ($this->stringValue($row->decided_at ?? null) !== '') {
                $events[] = $this->statusFeedItem($row, $users, 'correction', $this->stringValue($row->status ?? null), $this->stringValue($row->decided_at ?? null), $this->stringValue($row->request_type ?? null));
            }
        }

        return $events;
    }

    /**
     * @param  list<string>  $visibleUserPublicIds
     * @return array{visibleUsers: int, working: int, break: int, otherWork: int, noSession: int}
     */
    private function teamSummary(int $teamId, array $visibleUserPublicIds): array
    {
        $userIds = $this->visibleUserIds($visibleUserPublicIds);

        if ($userIds === []) {
            return $this->emptyTeamSummary();
        }

        $breakUserIds = $this->activeUserIds(TimeTrackingDatabaseTable::BREAKS, $teamId, $userIds);
        $otherWorkUserIds = $this->activeUserIds(TimeTrackingDatabaseTable::OTHER_WORK, $teamId, $userIds);
        $workUserIds = $this->activeUserIds(TimeTrackingDatabaseTable::WORK_SESSIONS, $teamId, $userIds);
        $lockedUserIds = array_values(array_unique([...$breakUserIds, ...$otherWorkUserIds]));
        $workingUserIds = array_values(array_diff($workUserIds, $lockedUserIds));

        return [
            'visibleUsers' => count($userIds),
            'working' => count($workingUserIds),
            'break' => count($breakUserIds),
            'otherWork' => count($otherWorkUserIds),
            'noSession' => count(array_diff($userIds, array_values(array_unique([...$workUserIds, ...$lockedUserIds])))),
        ];
    }

    /**
     * @param  list<string>  $visibleUserPublicIds
     * @return list<int>
     */
    private function visibleUserIds(array $visibleUserPublicIds): array
    {
        return array_keys($this->visibleUsers($visibleUserPublicIds));
    }

    /**
     * @param  list<string>  $visibleUserPublicIds
     * @return array<int, array{name: string, email: string}>
     */
    private function visibleUsers(array $visibleUserPublicIds): array
    {
        $publicIds = array_values(array_unique(array_filter($visibleUserPublicIds, static fn (string $publicId): bool => trim($publicId) !== '')));

        if ($publicIds === []) {
            return [];
        }

        $users = [];

        foreach ($this->database->table(IdentityDatabaseTable::USERS)->whereIn('public_id', $publicIds)->get(['id', 'name', 'email']) as $row) {
            $userId = $this->intValue($row->id ?? null);

            if ($userId < 1) {
                continue;
            }

            $users[$userId] = [
                'name' => $this->stringValue($row->name ?? null),
                'email' => $this->stringValue($row->email ?? null),
            ];
        }

        return $users;
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function activeUserIds(string $table, int $teamId, array $userIds): array
    {
        return array_values($this->database->table($table)
            ->where('team_id', $teamId)
            ->whereIn('user_id', $userIds)
            ->whereNull('ended_at')
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all());
    }

    /**
     * @param  array<int, array{name: string, email: string}>  $users
     * @return array{publicId: string, userName: string, userEmail: string, type: string, status: string, occurredAt: string, context: string}
     */
    private function statusFeedItem(object $row, array $users, string $type, string $status, string $occurredAt, string $context): array
    {
        $userId = $this->intValue($row->user_id ?? null);
        $user = $users[$userId] ?? ['name' => '', 'email' => ''];

        return [
            'publicId' => $this->stringValue($row->public_id ?? null).'-'.$type.'-'.$status.'-'.$occurredAt,
            'userName' => $user['name'],
            'userEmail' => $user['email'],
            'type' => $type,
            'status' => $status === '' ? 'updated' : $status,
            'occurredAt' => $occurredAt,
            'context' => $context === '' ? '-' : $context,
        ];
    }

    /**
     * @return array{visibleUsers: int, working: int, break: int, otherWork: int, noSession: int}
     */
    private function emptyTeamSummary(): array
    {
        return [
            'visibleUsers' => 0,
            'working' => 0,
            'break' => 0,
            'otherWork' => 0,
            'noSession' => 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function modules(array $rows): array
    {
        $modules = [];

        foreach ($rows as $row) {
            $context = is_scalar($row['context'] ?? null) ? (string) $row['context'] : '';

            if ($context !== '' && $context !== '-' && ! in_array($context, $modules, true)) {
                $modules[] = $context;
            }
        }

        sort($modules);

        return $modules;
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
