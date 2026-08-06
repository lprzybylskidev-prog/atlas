<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Optional\TimeTracking\Application\Contracts\BreakPolicyStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\UserTimeReport;
use App\Modules\Optional\TimeTracking\Application\DTOs\UserWorkTimeReport;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionSourceType;
use App\Modules\Optional\TimeTracking\Domain\Time\CalendarDayIntervalSplitter;
use App\Shared\Infrastructure\Database\DatabaseTable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * @phpstan-type ReportUser array{publicId: string, name: string, email: string}
 * @phpstan-type ReportFilters array{range: string, from: string, to: string, type: string, status: string, module: string, compare: string, team: string, user: string, live_status: string, closure_reason: string, review: string, category: string, decision_state: string, correction_type: string}
 * @phpstan-type TrackedAssignment array{userId: int, userPublicId: string, userName: string, userEmail: string, teamId: int, teamPublicId: string, teamName: string}
 */
final readonly class UserTimeReportService
{
    /**
     * @var array<string, string>
     */
    private const COMPARISON_METRICS = [
        'work' => 'workSeconds',
        'break' => 'breakSeconds',
        'other_work' => 'otherWorkSeconds',
    ];

    private const BUSINESS_TIMEZONE = 'Europe/Warsaw';

    private const MANUAL_CONTAINER_CLOSURE_REASON = 'manual_entry_container';

    public function __construct(
        private ConnectionInterface $database,
        private SettlementPeriodCoordinator $settlements,
        private BreakPolicyStore $breakPolicies,
        private CalendarDayIntervalSplitter $splitter = new CalendarDayIntervalSplitter,
    ) {}

    public function forRequest(Request $request, int $userId, int $teamId): UserTimeReport
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $rows = $this->rows([$userId], $teamId, $from, $to, $filters, []);
        $summary = $this->summary($rows);

        return new UserTimeReport(
            $rows,
            $summary,
            $filters,
            $this->comparison([$userId], $teamId, $from, $to, $filters, [], $summary),
        );
    }

    public function workTimeForRequest(Request $request, int $userId, int $teamId): UserWorkTimeReport
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $technicalRows = $this->rows([$userId], $teamId, $from, $to, $filters, []);
        $dailyRows = $this->dailyRows($userId, $teamId, $from, $to);
        $summary = $this->workTimeSummary($technicalRows, $dailyRows);

        return new UserWorkTimeReport(
            dailyRows: $dailyRows,
            otherWorkRows: $this->userOtherWorkDetails($userId, $teamId, $from, $to, $filters, true),
            summary: $summary,
            filters: $filters,
            comparison: $this->comparison([$userId], $teamId, $from, $to, $filters, [], $summary),
        );
    }

    /**
     * @param  list<string>  $visibleUserPublicIds
     */
    public function workTimeForManagerRequest(Request $request, int $teamId, array $visibleUserPublicIds): UserWorkTimeReport
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $users = $this->usersByPublicId($visibleUserPublicIds);
        $userIds = array_keys($users);
        $technicalRows = $userIds === []
            ? []
            : $this->rows($userIds, $teamId, $from, $to, $filters, $users);
        $dailyRows = $userIds === []
            ? []
            : $this->dailyRowsForUsers($userIds, $teamId, $from, $to, $users, $this->teamColumns($teamId));
        $summary = $this->workTimeSummary($technicalRows, $dailyRows);

        return new UserWorkTimeReport(
            dailyRows: $dailyRows,
            otherWorkRows: $userIds === [] ? [] : $this->otherWorkDetailsForUsers($userIds, $teamId, $from, $to, $filters, $users, $this->teamColumns($teamId)),
            summary: $summary,
            filters: $filters,
            comparison: $userIds === [] ? null : $this->comparison($userIds, $teamId, $from, $to, $filters, $users, $summary),
        );
    }

    public function workTimeForAdminRequest(Request $request): UserWorkTimeReport
    {
        $filters = $this->filters($request);

        $selectedTeamId = $this->adminSelectedTeamId($filters['team']);
        $assignments = $this->adminAssignments($filters, $selectedTeamId);

        return $this->workTimeForAssignmentsRequest($request, $assignments, $filters['user'] === '');
    }

    /**
     * @param  list<TrackedAssignment>  $assignments
     */
    public function workTimeForAssignmentsRequest(Request $request, array $assignments, bool $aggregateDailyRowsWhenNoUserSelected = false): UserWorkTimeReport
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $technicalRows = [];
        $dailyRows = [];
        $otherWorkRows = [];

        foreach ($this->assignmentsByTeam($assignments) as $teamId => $teamAssignments) {
            $users = [];

            foreach ($teamAssignments as $assignment) {
                $users[$assignment['userId']] = [
                    'publicId' => $assignment['userPublicId'],
                    'name' => $assignment['userName'],
                    'email' => $assignment['userEmail'],
                ];
            }

            $userIds = array_keys($users);
            $team = [
                'teamPublicId' => $teamAssignments[0]['teamPublicId'] ?? '',
                'teamName' => $teamAssignments[0]['teamName'] ?? '',
            ];

            $technicalRows = [
                ...$technicalRows,
                ...$this->rows($userIds, $teamId, $from, $to, $filters, $users),
            ];
            $teamDailyRows = $this->dailyRowsForUsers($userIds, $teamId, $from, $to, $users, $team);
            $dailyRows = [
                ...$dailyRows,
                ...($aggregateDailyRowsWhenNoUserSelected ? $this->aggregateDailyRowsForTeam($teamDailyRows, $team, $teamId) : $teamDailyRows),
            ];
            $otherWorkRows = [
                ...$otherWorkRows,
                ...$this->otherWorkDetailsForUsers($userIds, $teamId, $from, $to, $filters, $users, $team),
            ];
        }

        usort($dailyRows, fn (array $first, array $second): int => strcmp(
            $this->stringValue($second['date'] ?? null).$this->stringValue($second['userName'] ?? null),
            $this->stringValue($first['date'] ?? null).$this->stringValue($first['userName'] ?? null),
        ));
        usort($otherWorkRows, fn (array $first, array $second): int => strcmp(
            $this->stringValue($second['startedAt'] ?? null),
            $this->stringValue($first['startedAt'] ?? null),
        ));

        return new UserWorkTimeReport(
            dailyRows: $dailyRows,
            otherWorkRows: $otherWorkRows,
            summary: $this->workTimeSummary($technicalRows, $dailyRows),
            filters: $filters,
            comparison: null,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminWorkSessionRows(Request $request): array
    {
        $filters = $this->filters($request);

        $selectedTeamId = $this->adminSelectedTeamId($filters['team']);
        $assignments = $this->adminAssignments($filters, $selectedTeamId);

        return $this->workSessionRowsForAssignments($request, $assignments);
    }

    /**
     * @param  list<TrackedAssignment>  $assignments
     * @return list<array<string, mixed>>
     */
    public function workSessionRowsForAssignments(Request $request, array $assignments): array
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $assignmentByUserTeam = [];

        foreach ($assignments as $assignment) {
            $assignmentByUserTeam[$assignment['userId'].':'.$assignment['teamId']] = $assignment;
        }

        if ($assignmentByUserTeam === []) {
            return [];
        }

        $query = $this->database->table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS.' as sessions')
            ->where(static function (Builder $query) use ($assignmentByUserTeam): void {
                foreach ($assignmentByUserTeam as $assignment) {
                    $query->orWhere(static function (Builder $query) use ($assignment): void {
                        $query
                            ->where('sessions.user_id', $assignment['userId'])
                            ->where('sessions.team_id', $assignment['teamId']);
                    });
                }
            });
        $this->applyTimeRange($query, 'sessions.started_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('sessions.started_at')->get([
            'sessions.id',
            'sessions.public_id',
            'sessions.user_id',
            'sessions.team_id',
            'sessions.laravel_session_id',
            'sessions.started_at',
            'sessions.ended_at',
            'sessions.exact_seconds',
            'sessions.closure_reason',
        ]) as $row) {
            $assignment = $assignmentByUserTeam[$this->intValue($row->user_id ?? null).':'.$this->intValue($row->team_id ?? null)] ?? null;

            if ($assignment === null) {
                continue;
            }

            $sessionId = $this->intValue($row->id ?? null);
            $seconds = is_numeric($row->exact_seconds ?? null) ? (int) $row->exact_seconds : 0;
            $closureReason = $this->stringValue($row->closure_reason ?? null);
            $status = $this->stringValue($row->ended_at ?? null) === '' ? 'open' : 'closed';
            $maintenanceImpacts = $this->relatedCount(DatabaseTable::TIME_TRACKING_MAINTENANCE_AFFECTED_SESSIONS, 'work_session_id', $sessionId);

            if ($closureReason === self::MANUAL_CONTAINER_CLOSURE_REASON
                || ! $this->statusMatches($filters['status'], $status)
                || ! $this->closureReasonMatches($filters['closure_reason'], $closureReason)
                || ! $this->moduleMatches($filters['module'], $sessionId)
                || ! $this->reviewMatches($filters['review'], [
                    'requiresReview' => false,
                    'pendingDecision' => false,
                    'overdue' => false,
                    'maintenanceImpact' => $maintenanceImpacts > 0,
                ])
            ) {
                continue;
            }

            $rows[] = [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'userPublicId' => $assignment['userPublicId'],
                'userName' => $assignment['userName'],
                'userEmail' => $assignment['userEmail'],
                'teamPublicId' => $assignment['teamPublicId'],
                'teamName' => $assignment['teamName'],
                'startedAt' => $this->stringValue($row->started_at ?? null),
                'endedAt' => $this->stringValue($row->ended_at ?? null),
                'exactSeconds' => $seconds,
                'duration' => $this->duration($seconds),
                'closureReason' => $closureReason,
                'laravelSessionId' => $this->stringValue($row->laravel_session_id ?? null),
                'moduleSegments' => $this->relatedCount(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS, 'work_session_id', $sessionId),
                'relatedBreaks' => $this->relatedCount(DatabaseTable::TIME_TRACKING_BREAKS, 'work_session_id', $sessionId),
                'relatedOtherWork' => $this->relatedCount(DatabaseTable::TIME_TRACKING_OTHER_WORK, 'work_session_id', $sessionId),
                'maintenanceImpacts' => $maintenanceImpacts,
                'corrections' => $this->relatedCount(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS, 'work_session_id', $sessionId),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminBreakRows(Request $request): array
    {
        $filters = $this->filters($request);

        $selectedTeamId = $this->adminSelectedTeamId($filters['team']);
        $assignments = $this->adminAssignments($filters, $selectedTeamId);

        return $this->breakRowsForAssignments($request, $assignments);
    }

    /**
     * @param  list<TrackedAssignment>  $assignments
     * @return list<array<string, mixed>>
     */
    public function breakRowsForAssignments(Request $request, array $assignments): array
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $assignmentByUserTeam = $this->assignmentLookup($assignments);

        if ($assignmentByUserTeam === []) {
            return [];
        }

        $query = $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS.' as breaks')
            ->where(static function (Builder $query) use ($assignmentByUserTeam): void {
                foreach ($assignmentByUserTeam as $assignment) {
                    $query->orWhere(static function (Builder $query) use ($assignment): void {
                        $query
                            ->where('breaks.user_id', $assignment['userId'])
                            ->where('breaks.team_id', $assignment['teamId']);
                    });
                }
            });
        $this->applyTimeRange($query, 'breaks.started_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('breaks.started_at')->get([
            'breaks.id',
            'breaks.public_id',
            'breaks.user_id',
            'breaks.team_id',
            'breaks.started_at',
            'breaks.ended_at',
            'breaks.exact_seconds',
            'breaks.closure_reason',
            'breaks.requires_manager_review',
        ]) as $row) {
            $assignment = $assignmentByUserTeam[$this->intValue($row->user_id ?? null).':'.$this->intValue($row->team_id ?? null)] ?? null;

            if ($assignment === null) {
                continue;
            }

            $status = (bool) ($row->requires_manager_review ?? false) ? 'under_review' : ($this->stringValue($row->ended_at ?? null) === '' ? 'open' : 'closed');
            $closureReason = $this->stringValue($row->closure_reason ?? null);

            if ($closureReason === self::MANUAL_CONTAINER_CLOSURE_REASON
                || ! $this->statusMatches($filters['status'], $status)
                || ! $this->closureReasonMatches($filters['closure_reason'], $closureReason)
                || ! $this->reviewMatches($filters['review'], [
                    'requiresReview' => (bool) ($row->requires_manager_review ?? false),
                    'pendingDecision' => (bool) ($row->requires_manager_review ?? false),
                    'overdue' => false,
                    'maintenanceImpact' => false,
                ])
            ) {
                continue;
            }

            $seconds = $this->correctedSourceSeconds(CorrectionSourceType::Break, $this->intValue($row->id ?? null), $this->intValue($row->exact_seconds ?? null));
            $limit = $this->breakLimitState(
                userId: $this->intValue($row->user_id ?? null),
                teamId: $this->intValue($row->team_id ?? null),
                startedAt: $this->stringValue($row->started_at ?? null),
                endedAt: $this->stringValue($row->ended_at ?? null),
                requiresManagerReview: (bool) ($row->requires_manager_review ?? false),
                closureReason: $closureReason,
            );
            $rows[] = [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'userPublicId' => $assignment['userPublicId'],
                'userName' => $assignment['userName'],
                'userEmail' => $assignment['userEmail'],
                'teamPublicId' => $assignment['teamPublicId'],
                'teamName' => $assignment['teamName'],
                'status' => $status,
                'startedAt' => $this->stringValue($row->started_at ?? null),
                'endedAt' => $this->stringValue($row->ended_at ?? null),
                'exactSeconds' => $seconds,
                'duration' => $this->duration($seconds),
                'breakLimitStatus' => $limit['status'],
                'excessBreakSeconds' => $limit['excessSeconds'],
                'closureReason' => $closureReason,
                'requiresManagerReview' => (bool) ($row->requires_manager_review ?? false),
                'availableActions' => [
                    ...((bool) ($row->requires_manager_review ?? false) ? ['correct'] : []),
                    ...($limit['excessSeconds'] > 0 ? ['convert_excess'] : []),
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminCorrectionRows(Request $request): array
    {
        $filters = $this->filters($request);

        $selectedTeamId = $this->adminSelectedTeamId($filters['team']);
        $assignments = $this->adminAssignments($filters, $selectedTeamId);

        return $this->correctionRowsForAssignments($request, $assignments);
    }

    /**
     * @param  list<TrackedAssignment>  $assignments
     * @return list<array<string, mixed>>
     */
    public function correctionRowsForAssignments(Request $request, array $assignments): array
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $assignmentByUserTeam = $this->assignmentLookup($assignments);

        if ($assignmentByUserTeam === []) {
            return [];
        }

        $query = $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS.' as requests')
            ->where(static function (Builder $query) use ($assignmentByUserTeam): void {
                foreach ($assignmentByUserTeam as $assignment) {
                    $query->orWhere(static function (Builder $query) use ($assignment): void {
                        $query
                            ->where('requests.user_id', $assignment['userId'])
                            ->where('requests.team_id', $assignment['teamId']);
                    });
                }
            });
        $this->applyTimeRange($query, 'requests.requested_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('requests.requested_at')->get([
            'requests.id',
            'requests.public_id',
            'requests.user_id',
            'requests.team_id',
            'requests.source_type',
            'requests.source_id',
            'requests.request_type',
            'requests.status',
            'requests.description',
            'requests.requested_at',
            'requests.decided_at',
            'requests.decision_reason',
        ]) as $row) {
            $assignment = $assignmentByUserTeam[$this->intValue($row->user_id ?? null).':'.$this->intValue($row->team_id ?? null)] ?? null;
            $status = $this->stringValue($row->status ?? null);
            $type = $this->stringValue($row->request_type ?? null);
            $sourceType = $this->stringValue($row->source_type ?? null);
            $sourceId = $this->intValue($row->source_id ?? null);

            if ($assignment === null
                || ($filters['correction_type'] !== 'all' && $filters['correction_type'] !== $type)
                || ! $this->statusMatches($filters['status'], $status)
                || ! $this->reviewMatches($filters['review'], [
                    'requiresReview' => $status === 'pending',
                    'pendingDecision' => $status === 'pending',
                    'overdue' => false,
                    'maintenanceImpact' => false,
                ])
            ) {
                continue;
            }

            $proposal = $this->correctionProposal($this->intValue($row->id ?? null));
            $rows[] = [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'userPublicId' => $assignment['userPublicId'],
                'userName' => $assignment['userName'],
                'userEmail' => $assignment['userEmail'],
                'teamPublicId' => $assignment['teamPublicId'],
                'teamName' => $assignment['teamName'],
                'sourceType' => $sourceType,
                'sourcePublicId' => $this->sourcePublicId($sourceType, $sourceId),
                'type' => $type,
                'status' => $status,
                'description' => $this->stringValue($row->description ?? null),
                'requestedAt' => $this->stringValue($row->requested_at ?? null),
                'decidedAt' => $this->stringValue($row->decided_at ?? null),
                'decisionReason' => $this->stringValue($row->decision_reason ?? null),
                ...$proposal,
                'proposalCount' => $this->relatedCount(DatabaseTable::TIME_TRACKING_CORRECTION_PROPOSALS, 'correction_request_id', $this->intValue($row->id ?? null)),
                'historyCount' => $this->relatedCount(DatabaseTable::TIME_TRACKING_CORRECTION_HISTORY, 'correction_request_id', $this->intValue($row->id ?? null)),
                'availableActions' => $this->adminCorrectionActions($status),
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     originalStartedAt: string,
     *     originalEndedAt: string,
     *     originalExactSeconds: int|null,
     *     proposedStartedAt: string,
     *     proposedEndedAt: string,
     *     proposedExactSeconds: int|null,
     *     finalStartedAt: string,
     *     finalEndedAt: string,
     *     finalExactSeconds: int|null
     * }
     */
    private function correctionProposal(int $correctionRequestId): array
    {
        $proposal = $correctionRequestId < 1
            ? null
            : $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_PROPOSALS)
                ->where('correction_request_id', $correctionRequestId)
                ->first([
                    'original_started_at',
                    'original_ended_at',
                    'original_exact_seconds',
                    'proposed_started_at',
                    'proposed_ended_at',
                    'proposed_exact_seconds',
                    'final_started_at',
                    'final_ended_at',
                    'final_exact_seconds',
                ]);

        if (! is_object($proposal)) {
            return [
                'originalStartedAt' => '',
                'originalEndedAt' => '',
                'originalExactSeconds' => null,
                'proposedStartedAt' => '',
                'proposedEndedAt' => '',
                'proposedExactSeconds' => null,
                'finalStartedAt' => '',
                'finalEndedAt' => '',
                'finalExactSeconds' => null,
            ];
        }

        return [
            'originalStartedAt' => $this->stringValue($proposal->original_started_at ?? null),
            'originalEndedAt' => $this->stringValue($proposal->original_ended_at ?? null),
            'originalExactSeconds' => $this->nullableIntValue($proposal->original_exact_seconds ?? null),
            'proposedStartedAt' => $this->stringValue($proposal->proposed_started_at ?? null),
            'proposedEndedAt' => $this->stringValue($proposal->proposed_ended_at ?? null),
            'proposedExactSeconds' => $this->nullableIntValue($proposal->proposed_exact_seconds ?? null),
            'finalStartedAt' => $this->stringValue($proposal->final_started_at ?? null),
            'finalEndedAt' => $this->stringValue($proposal->final_ended_at ?? null),
            'finalExactSeconds' => $this->nullableIntValue($proposal->final_exact_seconds ?? null),
        ];
    }

    /**
     * @return list<string>
     */
    private function adminCorrectionActions(string $status): array
    {
        if ($status !== 'pending') {
            return [];
        }

        return ['reject', 'correct'];
    }

    private function sourcePublicId(string $sourceType, int $sourceId): string
    {
        if ($sourceId < 1) {
            return '';
        }

        $table = match ($sourceType) {
            'work_session' => DatabaseTable::TIME_TRACKING_WORK_SESSIONS,
            'break' => DatabaseTable::TIME_TRACKING_BREAKS,
            'other_work' => DatabaseTable::TIME_TRACKING_OTHER_WORK,
            default => null,
        };

        if ($table === null) {
            return '';
        }

        $publicId = $this->database->table($table)->where('id', $sourceId)->value('public_id');

        return is_string($publicId) ? $publicId : '';
    }

    /**
     * @return list<array{publicId: string, name: string, trackedUsers: int}>
     */
    public function adminTeamOptions(): array
    {
        $rows = [];

        foreach ($this->database->table(DatabaseTable::TIME_TRACKING_USER_TEAM_SETTINGS.' as settings')
            ->join(DatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments', 'settings.team_user_assignment_id', '=', 'assignments.id')
            ->join(DatabaseTable::TEAMS.' as teams', 'assignments.team_id', '=', 'teams.id')
            ->where('settings.tracking_enabled', true)
            ->groupBy('teams.id', 'teams.public_id', 'teams.name')
            ->orderBy('teams.name')
            ->get([
                'teams.public_id',
                'teams.name',
                $this->database->raw('count(distinct assignments.user_id) as tracked_users'),
            ]) as $row) {
            $rows[] = [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'name' => $this->stringValue($row->name ?? null),
                'trackedUsers' => $this->intValue($row->tracked_users ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{publicId: string, name: string, email: string}>
     */
    public function adminUserOptions(Request $request): array
    {
        $filters = $this->filters($request);
        $selectedTeamId = $this->adminSelectedTeamId($filters['team']);
        $rows = [];

        foreach ($selectedTeamId < 1 ? [] : $this->trackedAssignments($selectedTeamId) as $assignment) {
            $rows[] = [
                'publicId' => $assignment['userPublicId'],
                'name' => $assignment['userName'],
                'email' => $assignment['userEmail'],
            ];
        }

        usort($rows, fn (array $first, array $second): int => strcmp($first['name'], $second['name']));

        return $rows;
    }

    /**
     * @return array<string, list<array{publicId: string, name: string, email: string}>>
     */
    public function adminUserOptionsByTeam(): array
    {
        $teams = [];

        foreach ($this->trackedAssignments() as $assignment) {
            $teamPublicId = $assignment['teamPublicId'];

            if ($teamPublicId === '') {
                continue;
            }

            $teams[$teamPublicId] ??= [];
            $teams[$teamPublicId][] = [
                'publicId' => $assignment['userPublicId'],
                'name' => $assignment['userName'],
                'email' => $assignment['userEmail'],
            ];
        }

        foreach ($teams as $teamPublicId => $users) {
            usort($users, fn (array $first, array $second): int => strcmp($first['name'], $second['name']));
            $teams[$teamPublicId] = $users;
        }

        ksort($teams);

        return $teams;
    }

    /**
     * @return list<string>
     */
    public function adminModuleOptions(Request $request): array
    {
        $filters = $this->filters($request);
        $selectedTeamId = $this->adminSelectedTeamId($filters['team']);

        return $this->moduleOptionsForTeam($selectedTeamId < 1 ? null : $selectedTeamId);
    }

    /**
     * @return array<string, list<string>>
     */
    public function adminModuleOptionsByTeam(): array
    {
        $teams = [];

        foreach ($this->database->table(DatabaseTable::TIME_TRACKING_USER_TEAM_SETTINGS.' as settings')
            ->join(DatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments', 'settings.team_user_assignment_id', '=', 'assignments.id')
            ->join(DatabaseTable::TEAMS.' as teams', 'assignments.team_id', '=', 'teams.id')
            ->where('settings.tracking_enabled', true)
            ->distinct()
            ->orderBy('teams.public_id')
            ->pluck('teams.public_id', 'assignments.team_id') as $teamId => $teamPublicId) {
            $teamId = is_numeric($teamId) ? (int) $teamId : 0;
            $teamPublicId = $this->stringValue($teamPublicId);

            if ($teamId > 0 && $teamPublicId !== '') {
                $teams[$teamPublicId] = $this->moduleOptionsForTeam($teamId);
            }
        }

        ksort($teams);

        return $teams;
    }

    /**
     * @return list<array{key: string, labelPl: string, labelEn: string, teamPublicId: string}>
     */
    public function adminOtherWorkCategoryOptions(Request $request): array
    {
        $filters = $this->filters($request);
        $selectedTeamId = $this->adminSelectedTeamId($filters['team']);
        $trackedTeams = $this->trackedTeamPublicIdsById();

        if ($selectedTeamId > 0) {
            $trackedTeams = isset($trackedTeams[$selectedTeamId]) ? [$selectedTeamId => $trackedTeams[$selectedTeamId]] : [];
        }

        return $this->uniqueCategoryOptions($this->otherWorkCategoryOptionsForTeams($trackedTeams));
    }

    /**
     * @return array<string, list<array{key: string, labelPl: string, labelEn: string, teamPublicId: string}>>
     */
    public function adminOtherWorkCategoryOptionsByTeam(): array
    {
        $teams = [];

        foreach ($this->trackedTeamPublicIdsById() as $teamId => $teamPublicId) {
            $teams[$teamPublicId] = $this->otherWorkCategoryOptionsForTeams([$teamId => $teamPublicId]);
        }

        ksort($teams);

        return $teams;
    }

    /**
     * @return list<string>
     */
    private function moduleOptionsForTeam(?int $teamId): array
    {
        return ['System'];
    }

    /**
     * @param  list<string>  $visibleUserPublicIds
     */
    public function forManagerRequest(Request $request, int $teamId, array $visibleUserPublicIds): UserTimeReport
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $users = $this->usersByPublicId($visibleUserPublicIds);
        $userIds = array_keys($users);
        $rows = $userIds === []
            ? []
            : $this->rows($userIds, $teamId, $from, $to, $filters, $users);
        $summary = $this->summary($rows);

        return new UserTimeReport(
            $rows,
            $summary,
            $filters,
            $userIds === [] ? null : $this->comparison($userIds, $teamId, $from, $to, $filters, $users, $summary),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function userWorkSessionDetails(Request $request, int $userId, int $teamId): array
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $query = $this->database->table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)
            ->where('user_id', $userId)
            ->where('team_id', $teamId);
        $this->applyTimeRange($query, 'started_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('started_at')->get(['public_id', 'started_at', 'ended_at', 'exact_seconds']) as $row) {
            $status = $this->stringValue($row->ended_at ?? null) === '' ? 'open' : 'closed';

            if (! $this->statusMatches($filters['status'], $status)) {
                continue;
            }

            $seconds = is_numeric($row->exact_seconds ?? null) ? (int) $row->exact_seconds : 0;
            $rows[] = [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'sourceType' => 'work_session',
                'status' => $status,
                'startedAt' => $this->stringValue($row->started_at ?? null),
                'endedAt' => $this->stringValue($row->ended_at ?? null),
                'exactSeconds' => $seconds,
                'duration' => $this->duration($seconds),
                'availableActions' => ['request_correction'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function userBreakDetails(Request $request, int $userId, int $teamId): array
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $query = $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS)
            ->where('user_id', $userId)
            ->where('team_id', $teamId);
        $this->applyTimeRange($query, 'started_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('started_at')->get(['id', 'public_id', 'started_at', 'ended_at', 'exact_seconds', 'closure_reason', 'requires_manager_review']) as $row) {
            $status = $this->stringValue($row->ended_at ?? null) === '' ? 'open' : ((bool) ($row->requires_manager_review ?? false) ? 'under_review' : 'closed');

            if (! $this->statusMatches($filters['status'], $status)) {
                continue;
            }

            $seconds = $this->correctedSourceSeconds(CorrectionSourceType::Break, $this->intValue($row->id ?? null), $this->intValue($row->exact_seconds ?? null));
            $limit = $this->breakLimitState(
                userId: $userId,
                teamId: $teamId,
                startedAt: $this->stringValue($row->started_at ?? null),
                endedAt: $this->stringValue($row->ended_at ?? null),
                requiresManagerReview: (bool) ($row->requires_manager_review ?? false),
                closureReason: $this->stringValue($row->closure_reason ?? null),
            );
            $rows[] = [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'sourceType' => 'break',
                'status' => $status,
                'startedAt' => $this->stringValue($row->started_at ?? null),
                'endedAt' => $this->stringValue($row->ended_at ?? null),
                'exactSeconds' => $seconds,
                'duration' => $this->duration($seconds),
                'breakLimitStatus' => $limit['status'],
                'excessBreakSeconds' => $limit['excessSeconds'],
                'requiresManagerReview' => (bool) ($row->requires_manager_review ?? false),
                'availableActions' => ['request_correction'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function userCorrectionDetails(Request $request, int $userId, int $teamId): array
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->rangeBounds($filters);
        $query = $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)
            ->where('user_id', $userId)
            ->where('team_id', $teamId);
        $this->applyTimeRange($query, 'requested_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('requested_at')->get([
            'public_id',
            'source_type',
            'request_type',
            'status',
            'description',
            'requested_at',
            'decided_at',
            'decision_reason',
        ]) as $row) {
            $status = $this->stringValue($row->status ?? null);

            if (! $this->statusMatches($filters['status'], $status)) {
                continue;
            }

            $rows[] = [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'sourceType' => $this->stringValue($row->source_type ?? null),
                'type' => $this->stringValue($row->request_type ?? null),
                'status' => $status,
                'description' => $this->stringValue($row->description ?? null),
                'requestedAt' => $this->stringValue($row->requested_at ?? null),
                'decidedAt' => $this->stringValue($row->decided_at ?? null),
                'decisionReason' => $this->stringValue($row->decision_reason ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @return ReportFilters
     */
    private function filters(Request $request): array
    {
        return [
            'range' => $this->oneOf($request->query('range'), ['today', 'week', 'settlement_period', 'month', 'year', 'all', 'custom'], 'settlement_period'),
            'from' => $this->dateValue($request->query('from')),
            'to' => $this->dateValue($request->query('to')),
            'type' => $this->oneOf($request->query('type'), ['all', 'work', 'break', 'other_work', 'correction'], 'all'),
            'status' => $this->oneOf($request->query('status'), ['all', 'open', 'closed', 'pending', 'corrected', 'rejected', 'cancelled', 'under_review', 'approved'], 'all'),
            'module' => $this->stringValue($request->query('module')),
            'compare' => $this->oneOf($request->query('compare'), ['off', 'previous'], 'off'),
            'team' => $this->stringValue($request->query('team')),
            'user' => $this->stringValue($request->query('user')),
            'live_status' => $this->oneOf($request->query('live_status'), ['all', 'working', 'break', 'other_work', 'maintenance', 'offline', 'no_session'], 'all'),
            'closure_reason' => $this->stringValue($request->query('closure_reason')),
            'review' => $this->oneOf($request->query('review'), ['all', 'requires_review', 'pending_decision', 'maintenance_impact'], 'all'),
            'category' => $this->stringValue($request->query('category')),
            'decision_state' => $this->oneOf($request->query('decision_state'), ['all', 'final', 'requires_manager_review'], 'all'),
            'correction_type' => $this->oneOf($request->query('correction_type'), ['all', 'descriptive', 'exact_change', 'manual_entry', 'closed_period_override'], 'all'),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{0: ?DateTimeImmutable, 1: ?DateTimeImmutable}
     */
    private function rangeBounds(array $filters): array
    {
        $timezone = new DateTimeZone('Europe/Warsaw');
        $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0);

        if ($filters['range'] === 'custom') {
            return [
                $filters['from'] === '' ? null : new DateTimeImmutable($filters['from'].' 00:00:00', $timezone),
                $filters['to'] === '' ? null : new DateTimeImmutable($filters['to'].' 23:59:59', $timezone),
            ];
        }

        return match ($filters['range']) {
            'today' => [$today, $today->setTime(23, 59, 59)],
            'week' => [$today->modify('monday this week'), $today->modify('sunday this week')->setTime(23, 59, 59)],
            'month' => [$today->modify('first day of this month'), $today->modify('last day of this month')->setTime(23, 59, 59)],
            'year' => [$today->setDate((int) $today->format('Y'), 1, 1), $today->setDate((int) $today->format('Y'), 12, 31)->setTime(23, 59, 59)],
            'all' => [null, null],
            default => $this->settlementBounds($today),
        };
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function settlementBounds(DateTimeImmutable $today): array
    {
        $period = $this->settlements->periodFor($today);

        return [$period->startsOn, $period->endsOn->setTime(23, 59, 59)];
    }

    /**
     * @param  list<int>  $userIds
     * @param  ReportFilters  $filters
     * @param  array<int, ReportUser>  $users
     * @return list<array<string, mixed>>
     */
    private function rows(array $userIds, int $teamId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, array $filters, array $users): array
    {
        $rows = [
            ...$this->workRows($userIds, $teamId, $from, $to, $filters, $users),
            ...$this->breakRows($userIds, $teamId, $from, $to, $filters, $users),
            ...$this->otherWorkRows($userIds, $teamId, $from, $to, $filters, $users),
            ...$this->correctionRows($userIds, $teamId, $from, $to, $filters, $users),
        ];
        $rows = $filters['module'] === ''
            ? $rows
            : array_values(array_filter(
                $rows,
                fn (array $row): bool => $this->stringValue($row['context'] ?? null) === $filters['module'],
            ));

        usort($rows, fn (array $first, array $second): int => strcmp(
            $this->stringValue($second['startedAt'] ?? null),
            $this->stringValue($first['startedAt'] ?? null),
        ));

        return $rows;
    }

    /**
     * @param  list<int>  $userIds
     * @param  ReportFilters  $filters
     * @param  array<int, ReportUser>  $users
     * @return list<array<string, mixed>>
     */
    private function workRows(array $userIds, int $teamId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, array $filters, array $users): array
    {
        if ($filters['type'] !== 'all' && $filters['type'] !== 'work') {
            return [];
        }

        $query = $this->database->table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)
            ->whereIn('user_id', $userIds)
            ->where('team_id', $teamId);
        $this->applyTimeRange($query, 'started_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('started_at')->get(['public_id', 'user_id', 'started_at', 'ended_at', 'exact_seconds', 'closure_reason']) as $row) {
            $status = $this->stringValue($row->ended_at ?? null) === '' ? 'open' : 'closed';

            $closureReason = $this->stringValue($row->closure_reason ?? null);

            if (! $this->statusMatches($filters['status'], $status)
                || ! $this->closureReasonMatches($filters['closure_reason'], $closureReason)
                || ! $this->reviewMatches($filters['review'], [
                    'requiresReview' => (bool) ($row->requires_manager_review ?? false),
                    'pendingDecision' => (bool) ($row->requires_manager_review ?? false),
                    'overdue' => false,
                    'maintenanceImpact' => false,
                ])
            ) {
                continue;
            }

            $rows[] = $this->row($row, 'work', $status, 'System', $this->stringValue($row->closure_reason ?? null), $users);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dailyRows(int $userId, int $teamId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $days = [];

        foreach ($this->intervalRows(DatabaseTable::TIME_TRACKING_WORK_SESSIONS, $userId, $teamId, $from, $to, ['closure_reason']) as $row) {
            if ($this->stringValue($row->closure_reason ?? null) === self::MANUAL_CONTAINER_CLOSURE_REASON) {
                continue;
            }

            foreach ($this->slices($row, $from, $to) as $slice) {
                $day = $this->dailyRow($days, $slice['date']);
                $day['workSeconds'] = $this->intValue($day['workSeconds'] ?? null) + $slice['seconds'];
                $day['countedSeconds'] = $this->intValue($day['countedSeconds'] ?? null) + $slice['seconds'];
                $day['sessionStatus'] = $this->mergeDailyStatus($this->stringValue($day['sessionStatus'] ?? null), $this->stringValue($row->closure_reason ?? null));
                $days[$slice['date']] = $day;
            }
        }

        foreach ($this->intervalRows(DatabaseTable::TIME_TRACKING_BREAKS, $userId, $teamId, $from, $to, ['id', 'closure_reason', 'requires_manager_review']) as $row) {
            foreach ($this->slices($row, $from, $to) as $slice) {
                $day = $this->dailyRow($days, $slice['date']);
                $correctedSeconds = $this->correctedSourceSeconds(CorrectionSourceType::Break, $this->intValue($row->id ?? null), $this->intValue($row->exact_seconds ?? null));
                $sliceSeconds = $this->correctedSliceSeconds($slice['seconds'], $this->intValue($row->exact_seconds ?? null), $correctedSeconds);

                if ((bool) ($row->requires_manager_review ?? false) || ! $this->isRegularBreakClosure($this->stringValue($row->closure_reason ?? null))) {
                    $day['technicalBreakSeconds'] = $this->intValue($day['technicalBreakSeconds'] ?? null) + $sliceSeconds;
                } else {
                    $day['breakSeconds'] = $this->intValue($day['breakSeconds'] ?? null) + $sliceSeconds;
                }

                $days[$slice['date']] = $day;
            }
        }

        foreach ($this->intervalRows(DatabaseTable::TIME_TRACKING_OTHER_WORK, $userId, $teamId, $from, $to, ['approval_status']) as $row) {
            foreach ($this->slices($row, $from, $to) as $slice) {
                $day = $this->dailyRow($days, $slice['date']);
                $status = $this->stringValue($row->approval_status ?? null);

                $day['otherWorkSeconds'] = $this->intValue($day['otherWorkSeconds'] ?? null) + $slice['seconds'];

                if ($status === 'approved') {
                    $day['acceptedOtherWorkSeconds'] = $this->intValue($day['acceptedOtherWorkSeconds'] ?? null) + $slice['seconds'];
                    $day['countedSeconds'] = $this->intValue($day['countedSeconds'] ?? null) + $slice['seconds'];
                } else {
                    $day['pendingOtherWorkSeconds'] = $this->intValue($day['pendingOtherWorkSeconds'] ?? null) + $slice['seconds'];
                }

                $days[$slice['date']] = $day;
            }
        }

        foreach ($this->maintenanceRows($userId, $teamId, $from, $to) as $row) {
            foreach ($this->slices($row, $from, $to) as $slice) {
                $day = $this->dailyRow($days, $slice['date']);
                $day['maintenanceSeconds'] = $this->intValue($day['maintenanceSeconds'] ?? null) + $slice['seconds'];
                $day['countedSeconds'] = $this->intValue($day['countedSeconds'] ?? null) + $slice['seconds'];
                $days[$slice['date']] = $day;
            }
        }

        krsort($days);

        return array_map(fn (array $day): array => [
            ...$day,
            'countedDuration' => $this->duration($this->intValue($day['countedSeconds'] ?? null)),
            'workDuration' => $this->duration($this->intValue($day['workSeconds'] ?? null)),
            'breakDuration' => $this->duration($this->intValue($day['breakSeconds'] ?? null)),
            'technicalBreakDuration' => $this->duration($this->intValue($day['technicalBreakSeconds'])),
            'maintenanceDuration' => $this->duration($this->intValue($day['maintenanceSeconds'])),
            'otherWorkDuration' => $this->duration($this->intValue($day['otherWorkSeconds'])),
            'acceptedOtherWorkDuration' => $this->duration($this->intValue($day['acceptedOtherWorkSeconds'])),
            'pendingOtherWorkDuration' => $this->duration($this->intValue($day['pendingOtherWorkSeconds'])),
        ], array_values($days));
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<int, ReportUser>  $users
     * @param  array{teamPublicId: string, teamName: string}  $team
     * @return list<array<string, mixed>>
     */
    private function dailyRowsForUsers(array $userIds, int $teamId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, array $users, array $team): array
    {
        $rows = [];

        foreach ($userIds as $userId) {
            foreach ($this->dailyRows($userId, $teamId, $from, $to) as $row) {
                $rows[] = [
                    'publicId' => ($users[$userId]['publicId'] ?? 'user').'-'.($team['teamPublicId'] === '' ? (string) $teamId : $team['teamPublicId']).'-'.$this->stringValue($row['date'] ?? null),
                    'userPublicId' => $users[$userId]['publicId'] ?? '',
                    'userName' => $users[$userId]['name'] ?? '',
                    'userEmail' => $users[$userId]['email'] ?? '',
                    ...$team,
                    ...$row,
                ];
            }
        }

        usort($rows, fn (array $first, array $second): int => strcmp(
            $this->stringValue($second['date'] ?? null).$this->stringValue($second['userName'] ?? null),
            $this->stringValue($first['date'] ?? null).$this->stringValue($first['userName'] ?? null),
        ));

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{teamPublicId: string, teamName: string}  $team
     * @return list<array<string, mixed>>
     */
    private function aggregateDailyRowsForTeam(array $rows, array $team, int $teamId): array
    {
        $days = [];

        foreach ($rows as $row) {
            $date = $this->stringValue($row['date'] ?? null);

            if ($date === '') {
                continue;
            }

            $day = $days[$date] ?? [
                'publicId' => ($team['teamPublicId'] === '' ? (string) $teamId : $team['teamPublicId']).'-'.$date,
                'userPublicId' => '',
                'userName' => '',
                'userEmail' => '',
                ...$team,
                'date' => $date,
                'countedSeconds' => 0,
                'workSeconds' => 0,
                'breakSeconds' => 0,
                'technicalBreakSeconds' => 0,
                'maintenanceSeconds' => 0,
                'otherWorkSeconds' => 0,
                'acceptedOtherWorkSeconds' => 0,
                'pendingOtherWorkSeconds' => 0,
                'sessionStatus' => 'normal',
            ];

            foreach ([
                'countedSeconds',
                'workSeconds',
                'breakSeconds',
                'technicalBreakSeconds',
                'maintenanceSeconds',
                'otherWorkSeconds',
                'acceptedOtherWorkSeconds',
                'pendingOtherWorkSeconds',
            ] as $key) {
                $day[$key] += $this->intValue($row[$key] ?? null);
            }

            $day['sessionStatus'] = $this->mergeDailyStatus($day['sessionStatus'], $this->stringValue($row['sessionStatus'] ?? null));
            $days[$date] = $day;
        }

        krsort($days);

        return array_map(fn (array $day): array => [
            ...$day,
            'countedDuration' => $this->duration($day['countedSeconds']),
            'workDuration' => $this->duration($day['workSeconds']),
            'breakDuration' => $this->duration($day['breakSeconds']),
            'technicalBreakDuration' => $this->duration($this->intValue($day['technicalBreakSeconds'])),
            'maintenanceDuration' => $this->duration($this->intValue($day['maintenanceSeconds'])),
            'otherWorkDuration' => $this->duration($this->intValue($day['otherWorkSeconds'])),
            'acceptedOtherWorkDuration' => $this->duration($this->intValue($day['acceptedOtherWorkSeconds'])),
            'pendingOtherWorkDuration' => $this->duration($this->intValue($day['pendingOtherWorkSeconds'])),
        ], array_values($days));
    }

    /**
     * @param  array<string, array<string, mixed>>  $days
     * @return array<string, mixed>
     */
    private function dailyRow(array $days, string $date): array
    {
        return $days[$date] ?? [
            'date' => $date,
            'countedSeconds' => 0,
            'workSeconds' => 0,
            'breakSeconds' => 0,
            'technicalBreakSeconds' => 0,
            'maintenanceSeconds' => 0,
            'otherWorkSeconds' => 0,
            'acceptedOtherWorkSeconds' => 0,
            'pendingOtherWorkSeconds' => 0,
            'sessionStatus' => 'normal',
        ];
    }

    /**
     * @return list<object>
     */
    private function maintenanceRows(int $userId, int $teamId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $query = $this->database->table(DatabaseTable::TIME_TRACKING_MAINTENANCE_AFFECTED_SESSIONS.' as affected')
            ->join(DatabaseTable::TIME_TRACKING_MAINTENANCE_WINDOWS.' as windows', 'affected.maintenance_window_id', '=', 'windows.id')
            ->where('affected.user_id', $userId)
            ->where('affected.team_id', $teamId);

        if ($from !== null) {
            $query->where(static function (Builder $query) use ($from): void {
                $query
                    ->whereNull('windows.completed_at')
                    ->orWhere('windows.completed_at', '>=', $from)
                    ->orWhere('affected.returned_at', '>=', $from);
            });
        }

        if ($to !== null) {
            $query->where('affected.interrupted_at', '<=', $to);
        }

        $rows = [];

        foreach ($query->get(['affected.interrupted_at', 'affected.returned_at', 'affected.return_deadline_at', 'windows.completed_at']) as $row) {
            $startedAt = $this->stringValue($row->interrupted_at ?? null);
            $completedAt = $this->stringValue($row->completed_at ?? null);
            $returnedAt = $this->stringValue($row->returned_at ?? null);
            $returnDeadlineAt = $this->stringValue($row->return_deadline_at ?? null);
            $endedAt = $completedAt;

            if ($returnedAt !== '' && $returnDeadlineAt !== '' && new DateTimeImmutable($returnedAt) <= new DateTimeImmutable($returnDeadlineAt)) {
                $endedAt = $returnedAt;
            }

            if ($startedAt !== '') {
                $rows[] = (object) [
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>  $extraColumns
     * @return list<object>
     */
    private function intervalRows(
        string $table,
        int $userId,
        int $teamId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        array $extraColumns = [],
    ): array {
        $columns = array_values(array_unique(array_merge(['started_at', 'ended_at', 'exact_seconds'], $extraColumns)));
        $query = $this->database->table($table)
            ->where('user_id', $userId)
            ->where('team_id', $teamId);

        if ($from !== null) {
            $query->where(static function (Builder $query) use ($from): void {
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $from);
            });
        }

        if ($to !== null) {
            $query->where('started_at', '<=', $to);
        }

        return array_values($query->orderByDesc('started_at')->get($columns)->all());
    }

    /**
     * @return list<array{date: string, seconds: int}>
     */
    private function slices(object $row, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $startedAt = new DateTimeImmutable($this->stringValue($row->started_at ?? null));
        $endedAt = $this->stringValue($row->ended_at ?? null) === ''
            ? new DateTimeImmutable('now', new DateTimeZone(self::BUSINESS_TIMEZONE))
            : new DateTimeImmutable($this->stringValue($row->ended_at ?? null));

        if ($from !== null && $startedAt < $from) {
            $startedAt = $from;
        }

        if ($to !== null && $endedAt > $to) {
            $endedAt = $to;
        }

        if ($startedAt >= $endedAt) {
            return [];
        }

        return array_map(
            static fn (object $slice): array => [
                'date' => $slice->calendarDate,
                'seconds' => $slice->seconds,
            ],
            $this->splitter->split($startedAt, $endedAt),
        );
    }

    private function mergeDailyStatus(string $current, string $reason): string
    {
        if ($current !== 'normal' || $reason === '' || $reason === 'normal') {
            return $current;
        }

        return $reason;
    }

    /**
     * @param  list<int>  $userIds
     * @param  ReportFilters  $filters
     * @param  array<int, ReportUser>  $users
     * @return list<array<string, mixed>>
     */
    private function breakRows(array $userIds, int $teamId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, array $filters, array $users): array
    {
        if ($filters['type'] !== 'all' && $filters['type'] !== 'break') {
            return [];
        }

        $query = $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS)
            ->whereIn('user_id', $userIds)
            ->where('team_id', $teamId);
        $this->applyTimeRange($query, 'started_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('started_at')->get(['id', 'public_id', 'user_id', 'started_at', 'ended_at', 'exact_seconds', 'closure_reason', 'requires_manager_review']) as $row) {
            $status = (bool) ($row->requires_manager_review ?? false) ? 'under_review' : ($this->stringValue($row->ended_at ?? null) === '' ? 'open' : 'closed');
            $closureReason = $this->stringValue($row->closure_reason ?? null);

            if (! $this->statusMatches($filters['status'], $status)) {
                continue;
            }

            $row->exact_seconds = $this->correctedSourceSeconds(CorrectionSourceType::Break, $this->intValue($row->id ?? null), $this->intValue($row->exact_seconds ?? null));
            $limit = $this->breakLimitState(
                userId: $this->intValue($row->user_id ?? null),
                teamId: $teamId,
                startedAt: $this->stringValue($row->started_at ?? null),
                endedAt: $this->stringValue($row->ended_at ?? null),
                requiresManagerReview: (bool) ($row->requires_manager_review ?? false),
                closureReason: $closureReason,
            );

            $rows[] = [
                ...$this->row($row, 'break', $status, 'Break', $closureReason, $users),
                'breakLimitStatus' => $limit['status'],
                'excessBreakSeconds' => $limit['excessSeconds'],
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $userIds
     * @param  ReportFilters  $filters
     * @param  array<int, ReportUser>  $users
     * @return list<array<string, mixed>>
     */
    private function otherWorkRows(array $userIds, int $teamId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, array $filters, array $users): array
    {
        if ($filters['type'] !== 'all' && $filters['type'] !== 'other_work') {
            return [];
        }

        $query = $this->database->table(DatabaseTable::TIME_TRACKING_OTHER_WORK)
            ->whereIn('user_id', $userIds)
            ->where('team_id', $teamId);
        $this->applyTimeRange($query, 'started_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('started_at')->get(['public_id', 'user_id', 'category_key', 'started_at', 'ended_at', 'exact_seconds', 'closure_reason', 'approval_status', 'requires_manager_review']) as $row) {
            $status = $this->stringValue($row->approval_status ?? null);
            $closureReason = $this->stringValue($row->closure_reason ?? null);

            if (! $this->statusMatches($filters['status'], $status)
                || ! $this->closureReasonMatches($filters['closure_reason'], $closureReason)
                || ! $this->reviewMatches($filters['review'], [
                    'requiresReview' => (bool) ($row->requires_manager_review ?? false),
                    'pendingDecision' => (bool) ($row->requires_manager_review ?? false) || $status === 'pending',
                    'overdue' => false,
                    'maintenanceImpact' => false,
                ])
            ) {
                continue;
            }

            $rows[] = $this->row($row, 'other_work', $status, $this->stringValue($row->category_key ?? null), $closureReason, $users);
        }

        return $rows;
    }

    /**
     * @param  ReportFilters  $filters
     * @return list<array<string, mixed>>
     */
    private function userOtherWorkDetails(int $userId, int $teamId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, array $filters, bool $ownUserSurface = false): array
    {
        if ($filters['type'] !== 'all' && $filters['type'] !== 'other_work') {
            return [];
        }

        $categoryLabels = $this->otherWorkCategoryLabels($teamId);
        $query = $this->database->table(DatabaseTable::TIME_TRACKING_OTHER_WORK)
            ->where('user_id', $userId)
            ->where('team_id', $teamId);
        $this->applyTimeRange($query, 'started_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('started_at')->get([
            'public_id',
            'category_key',
            'description',
            'end_note',
            'approval_status',
            'requires_manager_review',
            'started_at',
            'ended_at',
            'exact_seconds',
            'closure_reason',
        ]) as $row) {
            $status = $this->stringValue($row->approval_status ?? null);
            $category = $this->stringValue($row->category_key ?? null);
            $decisionState = (bool) ($row->requires_manager_review ?? false) ? 'requires_manager_review' : 'final';
            $closureReason = $this->stringValue($row->closure_reason ?? null);

            if (! $this->categoryMatches($filters['category'], $category)
                || ! $this->decisionStateMatches($filters['decision_state'], $decisionState)
                || ! $this->statusMatches($filters['status'], $status)
                || ! $this->closureReasonMatches($filters['closure_reason'], $closureReason)
                || ! $this->reviewMatches($filters['review'], [
                    'requiresReview' => (bool) ($row->requires_manager_review ?? false),
                    'pendingDecision' => (bool) ($row->requires_manager_review ?? false) || $status === 'pending',
                    'overdue' => false,
                    'maintenanceImpact' => false,
                ])
            ) {
                continue;
            }

            $rows[] = [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'sourceType' => 'other_work',
                'category' => $category,
                'categoryLabelPl' => $categoryLabels[$category]['pl'] ?? '',
                'categoryLabelEn' => $categoryLabels[$category]['en'] ?? '',
                'description' => $this->stringValue($row->description ?? null),
                'endNote' => $this->stringValue($row->end_note ?? null),
                'status' => $status,
                'requiresManagerDecision' => (bool) ($row->requires_manager_review ?? false),
                'decisionState' => $decisionState,
                'availableActions' => $ownUserSurface
                    ? ['request_correction']
                    : ($decisionState === 'requires_manager_review' && $this->stringValue($row->ended_at ?? null) !== ''
                        ? ['approve', 'reject']
                        : []),
                'startedAt' => $this->stringValue($row->started_at ?? null),
                'endedAt' => $this->stringValue($row->ended_at ?? null),
                'exactSeconds' => is_numeric($row->exact_seconds ?? null) ? (int) $row->exact_seconds : 0,
                'duration' => $this->duration(is_numeric($row->exact_seconds ?? null) ? (int) $row->exact_seconds : 0),
                'closureReason' => $closureReason,
            ];
        }

        return $rows;
    }

    /**
     * @return array{status: string, excessSeconds: int}
     */
    private function breakLimitState(int $userId, int $teamId, string $startedAt, string $endedAt, bool $requiresManagerReview, string $closureReason): array
    {
        if ($userId <= 0 || $teamId <= 0 || $startedAt === '' || $endedAt === '' || $requiresManagerReview || ! $this->isRegularBreakClosure($closureReason)) {
            return ['status' => 'within_limit', 'excessSeconds' => 0];
        }

        $timezone = new DateTimeZone(self::BUSINESS_TIMEZONE);
        $startsAt = new DateTimeImmutable($startedAt, $timezone);
        $endsAt = new DateTimeImmutable($endedAt, $timezone);
        $dates = [];

        foreach ($this->splitter->split($startsAt, $endsAt) as $slice) {
            $dates[$slice->calendarDate] = true;
        }

        if ($dates === []) {
            return ['status' => 'within_limit', 'excessSeconds' => 0];
        }

        $firstDate = min(array_keys($dates));
        $lastDate = max(array_keys($dates));
        $windowStart = (new DateTimeImmutable($firstDate.' 00:00:00', $timezone));
        $windowEnd = (new DateTimeImmutable($lastDate.' 23:59:59', $timezone));
        $totalsByDate = array_fill_keys(array_keys($dates), 0);

        $rows = $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where('requires_manager_review', false)
            ->whereNotNull('ended_at')
            ->where('started_at', '<=', $windowEnd)
            ->where('ended_at', '>=', $windowStart)
            ->get(['id', 'started_at', 'ended_at', 'exact_seconds', 'closure_reason']);

        foreach ($rows as $row) {
            if (! $this->isRegularBreakClosure($this->stringValue($row->closure_reason ?? null))) {
                continue;
            }

            $rowStartedAt = new DateTimeImmutable($this->stringValue($row->started_at ?? null), $timezone);
            $rowEndedAt = new DateTimeImmutable($this->stringValue($row->ended_at ?? null), $timezone);
            $correctedSeconds = $this->correctedSourceSeconds(CorrectionSourceType::Break, $this->intValue($row->id ?? null), $this->intValue($row->exact_seconds ?? null));
            $rawSeconds = max(1, $rowEndedAt->getTimestamp() - $rowStartedAt->getTimestamp());

            foreach ($this->splitter->split($rowStartedAt, $rowEndedAt) as $slice) {
                if (array_key_exists($slice->calendarDate, $totalsByDate)) {
                    $totalsByDate[$slice->calendarDate] += $this->correctedSliceSeconds($slice->seconds, $rawSeconds, $correctedSeconds);
                }
            }
        }

        $dailyLimitSeconds = $this->breakPolicies->policyForUserTeam($userId, $teamId)->dailyLimitSeconds;
        $excessSeconds = 0;

        foreach ($totalsByDate as $seconds) {
            $excessSeconds += max(0, $seconds - $dailyLimitSeconds);
        }

        return [
            'status' => $excessSeconds > 0 ? 'exceeded' : 'within_limit',
            'excessSeconds' => $excessSeconds,
        ];
    }

    private function correctedSliceSeconds(int $sliceSeconds, int $rawSeconds, int $correctedSeconds): int
    {
        if ($rawSeconds <= 0 || $correctedSeconds >= $rawSeconds) {
            return $sliceSeconds;
        }

        return (int) floor($sliceSeconds * ($correctedSeconds / $rawSeconds));
    }

    private function correctedSourceSeconds(CorrectionSourceType $sourceType, int $sourceId, int $fallbackSeconds): int
    {
        if ($sourceId < 1) {
            return $fallbackSeconds;
        }

        $seconds = $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS.' as requests')
            ->join(DatabaseTable::TIME_TRACKING_CORRECTION_PROPOSALS.' as proposals', 'proposals.correction_request_id', '=', 'requests.id')
            ->where('requests.source_type', $sourceType->value)
            ->where('requests.source_id', $sourceId)
            ->where('requests.status', 'corrected')
            ->whereNotNull('proposals.final_exact_seconds')
            ->orderByDesc('requests.decided_at')
            ->orderByDesc('requests.id')
            ->value('proposals.final_exact_seconds');

        return is_numeric($seconds) ? (int) $seconds : $fallbackSeconds;
    }

    /**
     * @param  list<int>  $userIds
     * @param  ReportFilters  $filters
     * @param  array<int, ReportUser>  $users
     * @param  array{teamPublicId: string, teamName: string}  $team
     * @return list<array<string, mixed>>
     */
    private function otherWorkDetailsForUsers(
        array $userIds,
        int $teamId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        array $filters,
        array $users,
        array $team,
    ): array {
        $rows = [];

        foreach ($userIds as $userId) {
            foreach ($this->userOtherWorkDetails($userId, $teamId, $from, $to, $filters) as $row) {
                $rows[] = [
                    'userPublicId' => $users[$userId]['publicId'] ?? '',
                    'userName' => $users[$userId]['name'] ?? '',
                    'userEmail' => $users[$userId]['email'] ?? '',
                    ...$team,
                    ...$row,
                ];
            }
        }

        usort($rows, fn (array $first, array $second): int => strcmp(
            $this->stringValue($second['startedAt'] ?? null),
            $this->stringValue($first['startedAt'] ?? null),
        ));

        return $rows;
    }

    /**
     * @param  list<int>  $userIds
     * @param  ReportFilters  $filters
     * @param  array<int, ReportUser>  $users
     * @return list<array<string, mixed>>
     */
    private function correctionRows(array $userIds, int $teamId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, array $filters, array $users): array
    {
        if ($filters['type'] !== 'all' && $filters['type'] !== 'correction') {
            return [];
        }

        $query = $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)
            ->whereIn('user_id', $userIds)
            ->where('team_id', $teamId);
        $this->applyTimeRange($query, 'requested_at', $from, $to);

        $rows = [];

        foreach ($query->orderByDesc('requested_at')->get(['public_id', 'user_id', 'request_type', 'status', 'requested_at', 'decided_at', 'decision_reason']) as $row) {
            $status = $this->stringValue($row->status ?? null);

            if (! $this->statusMatches($filters['status'], $status)) {
                continue;
            }

            $rows[] = [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'userInternalId' => $this->intValue($row->user_id ?? null),
                ...$this->userColumns($row, $users),
                'type' => 'correction',
                'status' => $status,
                'context' => $this->stringValue($row->request_type ?? null),
                'startedAt' => $this->stringValue($row->requested_at ?? null),
                'endedAt' => $this->stringValue($row->decided_at ?? null),
                'exactSeconds' => 0,
                'reason' => $this->stringValue($row->decision_reason ?? null),
            ];
        }

        return $rows;
    }

    private function applyTimeRange(Builder $query, string $column, ?DateTimeImmutable $from, ?DateTimeImmutable $to): void
    {
        if ($from !== null) {
            $query->where($column, '>=', $from);
        }

        if ($to !== null) {
            $query->where($column, '<=', $to);
        }
    }

    /**
     * @param  list<int>  $userIds
     * @param  ReportFilters  $filters
     * @param  array<int, ReportUser>  $users
     * @param  array{totalSeconds: int, workSeconds: int, breakSeconds: int, otherWorkSeconds: int, corrections: int, pending: int}  $currentSummary
     * @return array{available: bool, rangeLabel: string, previousRangeLabel: string, metrics: list<array{metric: string, currentSeconds: int, previousSeconds: int, deltaSeconds: int, percentDelta: float|null}>, userMetrics: list<array{userPublicId: string, userName: string, metric: string, currentSeconds: int, previousSeconds: int, deltaSeconds: int, percentDelta: float|null}>}|null
     */
    private function comparison(
        array $userIds,
        int $teamId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        array $filters,
        array $users,
        array $currentSummary,
    ): ?array {
        if ($filters['compare'] !== 'previous' || $from === null || $to === null) {
            return null;
        }

        [$previousFrom, $previousTo] = $this->previousRangeBounds($filters['range'], $from, $to);
        $currentRows = $this->rows($userIds, $teamId, $from, $to, $filters, $users);
        $previousRows = $this->rows($userIds, $teamId, $previousFrom, $previousTo, $filters, $users);
        $previousSummary = $this->summary($previousRows);

        return [
            'available' => true,
            'rangeLabel' => $this->rangeLabel($from, $to),
            'previousRangeLabel' => $this->rangeLabel($previousFrom, $previousTo),
            'metrics' => $this->comparisonMetrics($currentSummary, $previousSummary),
            'userMetrics' => $this->comparisonUserMetrics($userIds, $users, $this->rowsByUser($currentRows), $this->rowsByUser($previousRows)),
        ];
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function previousRangeBounds(string $range, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return match ($range) {
            'today' => [$from->modify('-1 day'), $to->modify('-1 day')],
            'week' => [$from->modify('-1 week'), $to->modify('-1 week')],
            'settlement_period' => $this->settlementBounds($from->modify('-1 day')),
            'month' => [$from->modify('first day of previous month'), $from->modify('last day of previous month')->setTime(23, 59, 59)],
            'year' => [
                $from->setDate((int) $from->format('Y') - 1, 1, 1),
                $from->setDate((int) $from->format('Y') - 1, 12, 31)->setTime(23, 59, 59),
            ],
            default => $this->previousEqualLengthBounds($from, $to),
        };
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function previousEqualLengthBounds(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $seconds = max(0, $to->getTimestamp() - $from->getTimestamp());
        $previousTo = $from->modify('-1 second');

        return [$previousTo->modify('-'.$seconds.' seconds'), $previousTo];
    }

    private function rangeLabel(DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        return $from->format('Y-m-d').' - '.$to->format('Y-m-d');
    }

    /**
     * @param  array{totalSeconds: int, workSeconds: int, breakSeconds: int, otherWorkSeconds: int, corrections: int, pending: int}  $current
     * @param  array{totalSeconds: int, workSeconds: int, breakSeconds: int, otherWorkSeconds: int, corrections: int, pending: int}  $previous
     * @return list<array{metric: string, currentSeconds: int, previousSeconds: int, deltaSeconds: int, percentDelta: float|null}>
     */
    private function comparisonMetrics(array $current, array $previous): array
    {
        $metrics = [];

        foreach (self::COMPARISON_METRICS as $metric => $summaryKey) {
            $currentSeconds = $current[$summaryKey];
            $previousSeconds = $previous[$summaryKey];

            $metrics[] = [
                'metric' => $metric,
                'currentSeconds' => $currentSeconds,
                'previousSeconds' => $previousSeconds,
                'deltaSeconds' => $currentSeconds - $previousSeconds,
                'percentDelta' => $previousSeconds === 0 ? null : round((($currentSeconds - $previousSeconds) / $previousSeconds) * 100, 2),
            ];
        }

        return $metrics;
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<int, ReportUser>  $users
     * @param  array<int, array{workSeconds: int, breakSeconds: int, otherWorkSeconds: int}>  $current
     * @param  array<int, array{workSeconds: int, breakSeconds: int, otherWorkSeconds: int}>  $previous
     * @return list<array{userPublicId: string, userName: string, metric: string, currentSeconds: int, previousSeconds: int, deltaSeconds: int, percentDelta: float|null}>
     */
    private function comparisonUserMetrics(array $userIds, array $users, array $current, array $previous): array
    {
        $metrics = [];

        foreach ($userIds as $userId) {
            foreach (self::COMPARISON_METRICS as $metric => $summaryKey) {
                $currentSeconds = $current[$userId][$summaryKey] ?? 0;
                $previousSeconds = $previous[$userId][$summaryKey] ?? 0;

                if ($currentSeconds === 0 && $previousSeconds === 0) {
                    continue;
                }

                $metrics[] = [
                    'userPublicId' => $users[$userId]['publicId'] ?? '',
                    'userName' => $users[$userId]['name'] ?? '',
                    'metric' => $metric,
                    'currentSeconds' => $currentSeconds,
                    'previousSeconds' => $previousSeconds,
                    'deltaSeconds' => $currentSeconds - $previousSeconds,
                    'percentDelta' => $previousSeconds === 0 ? null : round((($currentSeconds - $previousSeconds) / $previousSeconds) * 100, 2),
                ];
            }
        }

        return $metrics;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array{workSeconds: int, breakSeconds: int, otherWorkSeconds: int}>
     */
    private function rowsByUser(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $userId = $this->intValue($row['userInternalId'] ?? null);

            if ($userId < 1) {
                continue;
            }

            $grouped[$userId] ??= [
                'workSeconds' => 0,
                'breakSeconds' => 0,
                'otherWorkSeconds' => 0,
            ];

            $seconds = is_numeric($row['exactSeconds'] ?? null) ? (int) $row['exactSeconds'] : 0;
            $type = $this->stringValue($row['type'] ?? null);

            if ($type === 'work') {
                $grouped[$userId]['workSeconds'] += $seconds;
            } elseif ($type === 'break') {
                $grouped[$userId]['breakSeconds'] += $seconds;
            } elseif ($type === 'other_work') {
                $grouped[$userId]['otherWorkSeconds'] += $seconds;
            }
        }

        return $grouped;
    }

    /**
     * @param  array<int, ReportUser>  $users
     * @return array<string, mixed>
     */
    private function row(object $row, string $type, string $status, string $context, string $reason, array $users): array
    {
        return [
            'publicId' => $this->stringValue($row->public_id ?? null),
            'userInternalId' => $this->intValue($row->user_id ?? null),
            ...$this->userColumns($row, $users),
            'type' => $type,
            'status' => $status,
            'context' => $context === '' ? '-' : $context,
            'startedAt' => $this->stringValue($row->started_at ?? null),
            'endedAt' => $this->stringValue($row->ended_at ?? null),
            'exactSeconds' => is_numeric($row->exact_seconds ?? null) ? (int) $row->exact_seconds : 0,
            'reason' => $reason,
        ];
    }

    /**
     * @param  list<string>  $userPublicIds
     * @return array<int, ReportUser>
     */
    private function usersByPublicId(array $userPublicIds): array
    {
        $publicIds = array_values(array_unique(array_filter($userPublicIds, fn (string $publicId): bool => trim($publicId) !== '')));

        if ($publicIds === []) {
            return [];
        }

        $users = [];

        foreach ($this->database->table(DatabaseTable::USERS)->whereIn('public_id', $publicIds)->get(['id', 'public_id', 'name', 'email']) as $user) {
            $id = $this->intValue($user->id ?? null);

            if ($id < 1) {
                continue;
            }

            $users[$id] = [
                'publicId' => $this->stringValue($user->public_id ?? null),
                'name' => $this->stringValue($user->name ?? null),
                'email' => $this->stringValue($user->email ?? null),
            ];
        }

        return $users;
    }

    /**
     * @param  ReportFilters  $filters
     * @return list<TrackedAssignment>
     */
    private function adminAssignments(array $filters, int $selectedTeamId): array
    {
        if ($selectedTeamId < 1) {
            return [];
        }

        return array_values(array_filter(
            $this->trackedAssignments($selectedTeamId),
            fn (array $assignment): bool => $filters['user'] === '' || $assignment['userPublicId'] === $filters['user'],
        ));
    }

    /**
     * @return list<TrackedAssignment>
     */
    private function trackedAssignments(?int $teamId = null): array
    {
        $rows = [];

        $query = $this->database->table(DatabaseTable::TIME_TRACKING_USER_TEAM_SETTINGS.' as settings')
            ->join(DatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments', 'settings.team_user_assignment_id', '=', 'assignments.id')
            ->join(DatabaseTable::USERS.' as users', 'assignments.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS.' as teams', 'assignments.team_id', '=', 'teams.id')
            ->where('settings.tracking_enabled', true);

        if ($teamId !== null) {
            $query->where('teams.id', $teamId);
        }

        foreach ($query->get([
            'users.id as user_id',
            'users.public_id as user_public_id',
            'users.name as user_name',
            'users.email as user_email',
            'teams.id as team_id',
            'teams.public_id as team_public_id',
            'teams.name as team_name',
        ]) as $row) {
            $userId = $this->intValue($row->user_id ?? null);
            $teamId = $this->intValue($row->team_id ?? null);

            if ($userId < 1 || $teamId < 1) {
                continue;
            }

            $rows[] = [
                'userId' => $userId,
                'userPublicId' => $this->stringValue($row->user_public_id ?? null),
                'userName' => $this->stringValue($row->user_name ?? null),
                'userEmail' => $this->stringValue($row->user_email ?? null),
                'teamId' => $teamId,
                'teamPublicId' => $this->stringValue($row->team_public_id ?? null),
                'teamName' => $this->stringValue($row->team_name ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<TrackedAssignment>  $assignments
     * @return array<string, TrackedAssignment>
     */
    private function assignmentLookup(array $assignments): array
    {
        $lookup = [];

        foreach ($assignments as $assignment) {
            $lookup[$assignment['userId'].':'.$assignment['teamId']] = $assignment;
        }

        return $lookup;
    }

    private function adminSelectedTeamId(string $teamPublicId): int
    {
        if ($teamPublicId === '') {
            return 0;
        }

        $id = $this->database->table(DatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->value('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    private function closureReasonMatches(string $filter, string $closureReason): bool
    {
        return $filter === '' || $closureReason === $filter;
    }

    private function categoryMatches(string $filter, string $category): bool
    {
        return $filter === ''
            || ($filter === '__none' && $category === '')
            || $category === $filter;
    }

    private function decisionStateMatches(string $filter, string $decisionState): bool
    {
        return $filter === 'all' || $decisionState === $filter;
    }

    private function moduleMatches(string $filter, int $workSessionId): bool
    {
        if ($filter === '') {
            return true;
        }

        if ($filter === 'System') {
            return true;
        }

        if ($workSessionId < 1) {
            return false;
        }

        return $this->database->table(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS)
            ->where('work_session_id', $workSessionId)
            ->where(static function (Builder $query) use ($filter): void {
                $query
                    ->where('module_key', $filter)
                    ->orWhere('context_key', $filter);
            })
            ->exists();
    }

    /**
     * @param  array{requiresReview: bool, pendingDecision: bool, overdue: bool, maintenanceImpact: bool}  $state
     */
    private function reviewMatches(string $filter, array $state): bool
    {
        return match ($filter) {
            'requires_review' => $state['requiresReview'],
            'pending_decision' => $state['pendingDecision'],
            'overdue' => $state['overdue'],
            'maintenance_impact' => $state['maintenanceImpact'],
            default => true,
        };
    }

    /**
     * @param  list<TrackedAssignment>  $assignments
     * @return array<int, list<TrackedAssignment>>
     */
    private function assignmentsByTeam(array $assignments): array
    {
        $grouped = [];

        foreach ($assignments as $assignment) {
            $grouped[$assignment['teamId']][] = $assignment;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array{teamPublicId: string, teamName: string}
     */
    private function teamColumns(int $teamId): array
    {
        $team = $this->database->table(DatabaseTable::TEAMS)
            ->where('id', $teamId)
            ->first(['public_id', 'name']);

        return [
            'teamPublicId' => $this->stringValue($team->public_id ?? null),
            'teamName' => $this->stringValue($team->name ?? null),
        ];
    }

    private function relatedCount(string $table, string $column, int $id): int
    {
        if ($id < 1) {
            return 0;
        }

        return (int) $this->database->table($table)->where($column, $id)->count();
    }

    /**
     * @param  array<int, ReportUser>  $users
     * @return array{userPublicId: string, userName: string, userEmail: string}
     */
    private function userColumns(object $row, array $users): array
    {
        $userId = $this->intValue($row->user_id ?? null);
        $user = $users[$userId] ?? null;

        return [
            'userPublicId' => $user['publicId'] ?? '',
            'userName' => $user['name'] ?? '',
            'userEmail' => $user['email'] ?? '',
        ];
    }

    /**
     * @param  array<int, string>  $teams
     * @return list<array{key: string, labelPl: string, labelEn: string, teamPublicId: string}>
     */
    private function otherWorkCategoryOptionsForTeams(array $teams): array
    {
        if ($teams === []) {
            return [];
        }

        $rows = [];

        foreach ($this->database->table(DatabaseTable::TIME_TRACKING_OTHER_WORK_CATEGORIES)
            ->where('scope_type', 'team')
            ->whereIn('scope_id', array_keys($teams))
            ->orderBy('label_pl')
            ->orderBy('category_key')
            ->get(['scope_id', 'category_key', 'label_pl', 'label_en']) as $row) {
            $key = $this->stringValue($row->category_key ?? null);
            $teamId = $this->intValue($row->scope_id ?? null);
            $teamPublicId = $teams[$teamId] ?? '';

            if ($key === '' || $teamPublicId === '') {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'labelPl' => $this->stringValue($row->label_pl ?? null),
                'labelEn' => $this->stringValue($row->label_en ?? null),
                'teamPublicId' => $teamPublicId,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{key: string, labelPl: string, labelEn: string, teamPublicId: string}>  $categories
     * @return list<array{key: string, labelPl: string, labelEn: string, teamPublicId: string}>
     */
    private function uniqueCategoryOptions(array $categories): array
    {
        $unique = [];

        foreach ($categories as $category) {
            $unique[$category['key']] ??= $category;
        }

        return array_values($unique);
    }

    /**
     * @return array<string, array{pl: string, en: string}>
     */
    private function otherWorkCategoryLabels(int $teamId): array
    {
        $labels = [];

        foreach ($this->database->table(DatabaseTable::TIME_TRACKING_OTHER_WORK_CATEGORIES)
            ->where('scope_type', 'team')
            ->where('scope_id', $teamId)
            ->get(['category_key', 'label_pl', 'label_en']) as $row) {
            $key = $this->stringValue($row->category_key ?? null);

            if ($key !== '') {
                $labels[$key] = [
                    'pl' => $this->stringValue($row->label_pl ?? null),
                    'en' => $this->stringValue($row->label_en ?? null),
                ];
            }
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    private function trackedTeamPublicIdsById(): array
    {
        $teams = [];

        foreach ($this->database->table(DatabaseTable::TIME_TRACKING_USER_TEAM_SETTINGS.' as settings')
            ->join(DatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments', 'settings.team_user_assignment_id', '=', 'assignments.id')
            ->join(DatabaseTable::TEAMS.' as teams', 'assignments.team_id', '=', 'teams.id')
            ->where('settings.tracking_enabled', true)
            ->distinct()
            ->orderBy('teams.public_id')
            ->get(['assignments.team_id', 'teams.public_id']) as $row) {
            $teamId = $this->intValue($row->team_id ?? null);
            $teamPublicId = $this->stringValue($row->public_id ?? null);

            if ($teamId > 0 && $teamPublicId !== '') {
                $teams[$teamId] = $teamPublicId;
            }
        }

        return $teams;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{totalSeconds: int, workSeconds: int, breakSeconds: int, otherWorkSeconds: int, corrections: int, pending: int}
     */
    private function summary(array $rows): array
    {
        $summary = [
            'totalSeconds' => 0,
            'workSeconds' => 0,
            'breakSeconds' => 0,
            'otherWorkSeconds' => 0,
            'corrections' => 0,
            'pending' => 0,
        ];

        foreach ($rows as $row) {
            $seconds = is_numeric($row['exactSeconds'] ?? null) ? (int) $row['exactSeconds'] : 0;
            $type = $this->stringValue($row['type'] ?? null);
            $status = $this->stringValue($row['status'] ?? null);

            if ($type === 'work') {
                $summary['workSeconds'] += $seconds;
                $summary['totalSeconds'] += $seconds;
            } elseif ($type === 'break') {
                $summary['breakSeconds'] += $seconds;
            } elseif ($type === 'other_work') {
                $summary['otherWorkSeconds'] += $seconds;
                $summary['totalSeconds'] += $seconds;
            } elseif ($type === 'correction') {
                $summary['corrections']++;
            }

            if ($status === 'pending') {
                $summary['pending']++;
            }
        }

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $dailyRows
     * @return array{totalSeconds: int, workSeconds: int, breakSeconds: int, technicalBreakSeconds: int, maintenanceSeconds: int, otherWorkSeconds: int, acceptedOtherWorkSeconds: int, pendingOtherWorkSeconds: int, corrections: int, pending: int}
     */
    private function workTimeSummary(array $rows, array $dailyRows): array
    {
        $summary = [
            ...$this->summary($rows),
            'technicalBreakSeconds' => 0,
            'maintenanceSeconds' => 0,
            'acceptedOtherWorkSeconds' => 0,
            'pendingOtherWorkSeconds' => 0,
        ];

        $summary['totalSeconds'] = 0;
        $summary['workSeconds'] = 0;
        $summary['breakSeconds'] = 0;
        $summary['otherWorkSeconds'] = 0;

        foreach ($dailyRows as $row) {
            $summary['totalSeconds'] += $this->intValue($row['countedSeconds'] ?? null);
            $summary['workSeconds'] += $this->intValue($row['workSeconds'] ?? null);
            $summary['breakSeconds'] += $this->intValue($row['breakSeconds'] ?? null);
            $summary['technicalBreakSeconds'] += $this->intValue($row['technicalBreakSeconds'] ?? null);
            $summary['maintenanceSeconds'] += $this->intValue($row['maintenanceSeconds'] ?? null);
            $summary['otherWorkSeconds'] += $this->intValue($row['otherWorkSeconds'] ?? null);
            $summary['acceptedOtherWorkSeconds'] += $this->intValue($row['acceptedOtherWorkSeconds'] ?? null);
            $summary['pendingOtherWorkSeconds'] += $this->intValue($row['pendingOtherWorkSeconds'] ?? null);
        }

        return $summary;
    }

    private function isRegularBreakClosure(string $reason): bool
    {
        return $reason === '' || $reason === 'normal' || $reason === 'user_returned';
    }

    private function statusMatches(string $filter, string $status): bool
    {
        return $filter === 'all' || $filter === $status;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed, string $default): string
    {
        $string = $this->stringValue($value);

        return in_array($string, $allowed, true) ? $string : $default;
    }

    private function dateValue(mixed $value): string
    {
        $string = $this->stringValue($value);

        return preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $string) === 1 ? $string : '';
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableIntValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function duration(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
