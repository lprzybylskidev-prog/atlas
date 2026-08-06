<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Persistence\AuditDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Contracts\BreakPolicyStore;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionSourceType;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Modules\Optional\TimeTracking\Domain\Time\CalendarDayIntervalSplitter;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final readonly class AdminTimeTrackingOperationDetailController
{
    private const TIMEZONE = 'Europe/Warsaw';

    public function __construct(
        private ConnectionInterface $database,
        private UserTimeReportService $reports,
        private BreakPolicyStore $breakPolicies,
        private ManagerHierarchy $hierarchy,
    ) {}

    public function workSession(Request $request, string $session): Response
    {
        $record = $this->database->table(TimeTrackingDatabaseTable::WORK_SESSIONS.' as sessions')
            ->join(IdentityDatabaseTable::USERS.' as users', 'sessions.user_id', '=', 'users.id')
            ->join(TeamsDatabaseTable::TEAMS.' as teams', 'sessions.team_id', '=', 'teams.id')
            ->where('sessions.public_id', $session)
            ->first([
                'sessions.id',
                'sessions.public_id',
                'users.public_id as user_public_id',
                'users.name as user_name',
                'users.email as user_email',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'sessions.laravel_session_id',
                'sessions.started_at',
                'sessions.ended_at',
                'sessions.exact_seconds',
                'sessions.closure_reason',
                'sessions.created_at',
                'sessions.updated_at',
            ]);

        $record = $this->ensureRecordForAuthorizedSurface($request, $record);
        $sessionId = $this->intValue($record->id ?? null);
        $summaryRecord = (object) [
            ...(array) $record,
            'available_actions' => $this->stringValue($record->ended_at ?? null) === '' ? 'terminate' : '',
        ];

        return $this->render(
            request: $request,
            component: 'TimeTracking/AdminWorkSessionDetail',
            kind: 'work_session',
            title: 'pages.time_tracking.admin_detail.work_session_title',
            backHref: $this->sectionRoute($request, 'work_sessions'),
            record: $this->summary($summaryRecord, ['id']),
            sections: [
                $this->section('pages.time_tracking.admin_detail.sections.module_segments', $this->rows(TimeTrackingDatabaseTable::MODULE_CONTEXT_SEGMENTS, [
                    'work_session_id' => $sessionId,
                ], ['public_id', 'module_key', 'context_key', 'started_at', 'ended_at', 'exact_seconds', 'created_at'])),
                $this->section('pages.time_tracking.admin_detail.sections.breaks', $this->rows(TimeTrackingDatabaseTable::BREAKS, [
                    'work_session_id' => $sessionId,
                ], ['public_id', 'started_at', 'ended_at', 'exact_seconds', 'closure_reason', 'requires_manager_review', 'created_at'])),
                $this->section('pages.time_tracking.admin_detail.sections.other_work', $this->otherWorkRows($sessionId)),
                $this->section('pages.time_tracking.admin_detail.sections.maintenance', $this->maintenanceRows($sessionId)),
                $this->section('pages.time_tracking.admin_detail.sections.corrections', $this->rows(TimeTrackingDatabaseTable::CORRECTION_REQUESTS, [
                    'work_session_id' => $sessionId,
                ], ['public_id', 'request_type', 'status', 'description', 'requested_at', 'decided_at', 'decision_reason'])),
                $this->auditSection($this->stringValue($record->public_id ?? null)),
            ],
        );
    }

    public function break(Request $request, string $break): Response
    {
        $record = $this->database->table(TimeTrackingDatabaseTable::BREAKS.' as breaks')
            ->join(IdentityDatabaseTable::USERS.' as users', 'breaks.user_id', '=', 'users.id')
            ->join(TeamsDatabaseTable::TEAMS.' as teams', 'breaks.team_id', '=', 'teams.id')
            ->leftJoin(TimeTrackingDatabaseTable::WORK_SESSIONS.' as sessions', 'breaks.work_session_id', '=', 'sessions.id')
            ->where('breaks.public_id', $break)
            ->first([
                'breaks.id',
                'breaks.public_id',
                'breaks.user_id',
                'breaks.team_id',
                'sessions.public_id as work_session_public_id',
                'users.public_id as user_public_id',
                'users.name as user_name',
                'users.email as user_email',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'breaks.started_at',
                'breaks.ended_at',
                'breaks.exact_seconds',
                'breaks.closure_reason',
                'breaks.requires_manager_review',
                'breaks.created_at',
                'breaks.updated_at',
            ]);

        $record = $this->ensureRecordForAuthorizedSurface($request, $record);
        $exactSeconds = $this->correctedSourceSeconds(CorrectionSourceType::Break, $this->intValue($record->id ?? null), $this->intValue($record->exact_seconds ?? null));
        $excessBreakSeconds = $this->breakExcessSeconds($record);
        $summaryRecord = (object) [
            ...(array) $record,
            'exact_seconds' => $exactSeconds,
            'excess_break_seconds' => $excessBreakSeconds,
            'available_actions' => implode(',', [
                ...($this->stringValue($record->ended_at ?? null) === '' ? ['force_close_break'] : []),
                ...($excessBreakSeconds > 0 ? ['convert_excess_break'] : []),
            ]),
        ];

        return $this->render($request, 'TimeTracking/AdminBreakDetail', 'break', 'pages.time_tracking.admin_detail.break_title', $this->sectionRoute($request, 'breaks'), $this->summary($summaryRecord, ['id', 'user_id', 'team_id']), [
            $this->auditSection($break),
        ]);
    }

    public function otherWork(Request $request, string $otherWork): Response
    {
        $record = $this->database->table(TimeTrackingDatabaseTable::OTHER_WORK.' as other_work')
            ->join(IdentityDatabaseTable::USERS.' as users', 'other_work.user_id', '=', 'users.id')
            ->join(TeamsDatabaseTable::TEAMS.' as teams', 'other_work.team_id', '=', 'teams.id')
            ->leftJoin(TimeTrackingDatabaseTable::WORK_SESSIONS.' as sessions', 'other_work.work_session_id', '=', 'sessions.id')
            ->leftJoin(TimeTrackingDatabaseTable::OTHER_WORK_CATEGORIES.' as categories', function (JoinClause $join): void {
                $join
                    ->on('categories.scope_id', '=', 'other_work.team_id')
                    ->on('categories.category_key', '=', 'other_work.category_key')
                    ->where('categories.scope_type', '=', 'team');
            })
            ->where('other_work.public_id', $otherWork)
            ->first([
                'other_work.public_id',
                'sessions.public_id as work_session_public_id',
                'users.public_id as user_public_id',
                'users.name as user_name',
                'users.email as user_email',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'other_work.category_key',
                app()->getLocale() === 'pl' ? 'categories.label_pl as category_label' : 'categories.label_en as category_label',
                'other_work.description',
                'other_work.end_note',
                'other_work.approval_status',
                'other_work.started_at',
                'other_work.ended_at',
                'other_work.exact_seconds',
                'other_work.closure_reason',
                'other_work.requires_manager_review',
                'other_work.created_at',
                'other_work.updated_at',
            ]);

        $record = $this->ensureRecordForAuthorizedSurface($request, $record);

        return $this->render($request, 'TimeTracking/AdminOtherWorkDetail', 'other_work', 'pages.time_tracking.admin_detail.other_work_title', $this->sectionRoute($request, 'other_work'), $this->summary($record), [
            $this->auditSection($otherWork),
        ]);
    }

    public function correction(Request $request, string $correction): Response
    {
        $record = $this->database->table(TimeTrackingDatabaseTable::CORRECTION_REQUESTS.' as requests')
            ->join(IdentityDatabaseTable::USERS.' as users', 'requests.user_id', '=', 'users.id')
            ->join(TeamsDatabaseTable::TEAMS.' as teams', 'requests.team_id', '=', 'teams.id')
            ->leftJoin(TimeTrackingDatabaseTable::WORK_SESSIONS.' as sessions', 'requests.work_session_id', '=', 'sessions.id')
            ->where('requests.public_id', $correction)
            ->first([
                'requests.id',
                'requests.public_id',
                'sessions.public_id as work_session_public_id',
                'requests.source_type',
                'requests.source_id',
                'users.public_id as user_public_id',
                'users.name as user_name',
                'users.email as user_email',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'requests.request_type',
                'requests.status',
                'requests.description',
                'requests.requested_at',
                'requests.cancelled_at',
                'requests.cancellation_reason',
                'requests.decided_at',
                'requests.decision_reason',
                'requests.created_at',
                'requests.updated_at',
            ]);

        $record = $this->ensureRecordForAuthorizedSurface($request, $record);
        $correctionId = $this->intValue($record->id ?? null);
        $summaryRecord = (object) [
            ...(array) $record,
            'available_actions' => implode(',', $this->correctionActions($this->stringValue($record->status ?? null))),
        ];

        return $this->render($request, 'TimeTracking/AdminCorrectionDetail', 'correction', 'pages.time_tracking.admin_detail.correction_title', $this->sectionRoute($request, 'corrections'), $this->summary($summaryRecord, ['id']), [
            $this->section('pages.time_tracking.admin_detail.sections.proposals', $this->rows(TimeTrackingDatabaseTable::CORRECTION_PROPOSALS, [
                'correction_request_id' => $correctionId,
            ], ['public_id', 'original_started_at', 'original_ended_at', 'original_exact_seconds', 'proposed_started_at', 'proposed_ended_at', 'proposed_exact_seconds', 'final_started_at', 'final_ended_at', 'final_exact_seconds'])),
            $this->section('pages.time_tracking.admin_detail.sections.history', $this->correctionHistoryRows($correctionId)),
            $this->section('pages.time_tracking.admin_detail.sections.closed_period_overrides', $this->rows(TimeTrackingDatabaseTable::CLOSED_PERIOD_OVERRIDES, [
                'correction_request_id' => $correctionId,
            ], ['public_id', 'actor_user_id', 'actor_scope', 'admin_mode_confirmed', 'high_risk_reauthenticated', 'mfa_confirmed', 'before_after_preview_confirmed', 'reason', 'authorized_at'])),
            $this->auditSection($correction),
        ]);
    }

    /**
     * @param  list<array{key: string, value: string}>  $record
     * @param  list<array{title: string, rows: list<array<string, string>>}>  $sections
     */
    private function render(Request $request, string $component, string $kind, string $title, string $backHref, array $record, array $sections): Response
    {
        return Inertia::render($component, [
            'surface' => $this->surface($request),
            'kind' => $kind,
            'title' => $title,
            'backHref' => $backHref,
            'record' => $record,
            'sections' => $sections,
        ]);
    }

    private function ensureRecordForAuthorizedSurface(Request $request, ?object $record): object
    {
        if ($record === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $teamPublicId = $this->stringValue($record->team_public_id ?? null);

        if ($this->isManagerRoute($request)) {
            $userPublicId = $this->stringValue($record->user_public_id ?? null);
            $managerUserPublicId = data_get($request->user(), 'public_id');

            if ($teamPublicId === '' || $userPublicId === '' || ! is_string($managerUserPublicId)) {
                abort(HttpResponse::HTTP_FORBIDDEN);
            }

            $scope = $this->hierarchy->scopeFor($teamPublicId, $managerUserPublicId);

            if (! in_array($userPublicId, $scope->visibleUserPublicIds, true)) {
                abort(HttpResponse::HTTP_FORBIDDEN);
            }

            return $record;
        }

        if ($teamPublicId === '' || ! in_array($teamPublicId, $this->adminTeamPublicIds(), true)) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }

        return $record;
    }

    private function sectionRoute(Request $request, string $section): string
    {
        if ($this->isManagerRoute($request)) {
            return match ($section) {
                'breaks' => route('manager.work-time.breaks.index'),
                'corrections' => route('manager.work-time.corrections.index'),
                'other_work' => route('manager.work-time.other-work.index'),
                default => route('manager.work-time.work-sessions.index'),
            };
        }

        return match ($section) {
            'breaks' => route('admin.work-time.breaks.index'),
            'corrections' => route('admin.work-time.corrections.index'),
            'other_work' => route('admin.work-time.other-work.index'),
            default => route('admin.work-time.work-sessions.index'),
        };
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

    /**
     * @return list<string>
     */
    private function adminTeamPublicIds(): array
    {
        return array_values(array_filter(array_map(
            fn (array $team): string => $this->stringValue($team['publicId']),
            $this->reports->adminTeamOptions(),
        )));
    }

    /**
     * @param  list<string>  $hiddenKeys
     * @return list<array{key: string, value: string}>
     */
    private function summary(object $record, array $hiddenKeys = []): array
    {
        $summary = [];

        foreach ((array) $record as $key => $value) {
            if (in_array($key, $hiddenKeys, true)) {
                continue;
            }

            $summary[] = [
                'key' => $key,
                'value' => $this->displayValue($value),
            ];
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $where
     * @param  list<string>  $columns
     * @return list<array<string, string>>
     */
    private function rows(string $table, array $where, array $columns): array
    {
        $query = $this->database->table($table);

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        return array_values(array_map(
            fn (object $row): array => $this->row($row),
            $query->orderByDesc('created_at')->get($columns)->all(),
        ));
    }

    /**
     * @return list<array<string, string>>
     */
    private function maintenanceRows(int $workSessionId): array
    {
        return array_values(array_map(
            fn (object $row): array => $this->row($row),
            $this->database->table(TimeTrackingDatabaseTable::MAINTENANCE_AFFECTED_SESSIONS.' as affected')
                ->join(TimeTrackingDatabaseTable::MAINTENANCE_WINDOWS.' as windows', 'affected.maintenance_window_id', '=', 'windows.id')
                ->where('affected.work_session_id', $workSessionId)
                ->orderByDesc('affected.created_at')
                ->get([
                    'affected.public_id',
                    'windows.public_id as maintenance_window_public_id',
                    'windows.kind',
                    'windows.status',
                    'windows.started_at',
                    'windows.completed_at',
                    'affected.interrupted_at',
                    'affected.return_deadline_at',
                    'affected.returned_at',
                ])
                ->all(),
        ));
    }

    /**
     * @return list<array<string, string>>
     */
    private function otherWorkRows(int $workSessionId): array
    {
        return array_values(array_map(
            fn (object $row): array => $this->row($row),
            $this->database->table(TimeTrackingDatabaseTable::OTHER_WORK.' as other_work')
                ->leftJoin(TimeTrackingDatabaseTable::OTHER_WORK_CATEGORIES.' as categories', function (JoinClause $join): void {
                    $join
                        ->on('categories.scope_id', '=', 'other_work.team_id')
                        ->on('categories.category_key', '=', 'other_work.category_key')
                        ->where('categories.scope_type', '=', 'team');
                })
                ->where('other_work.work_session_id', $workSessionId)
                ->orderByDesc('other_work.created_at')
                ->get([
                    'other_work.public_id',
                    'other_work.category_key',
                    app()->getLocale() === 'pl' ? 'categories.label_pl as category_label' : 'categories.label_en as category_label',
                    'other_work.description',
                    'other_work.end_note',
                    'other_work.approval_status',
                    'other_work.started_at',
                    'other_work.ended_at',
                    'other_work.exact_seconds',
                    'other_work.closure_reason',
                    'other_work.requires_manager_review',
                ])
                ->all(),
        ));
    }

    /**
     * @return list<array<string, string>>
     */
    private function correctionHistoryRows(int $correctionId): array
    {
        return array_values(array_map(
            fn (object $row): array => $this->row($row),
            $this->database->table(TimeTrackingDatabaseTable::CORRECTION_HISTORY.' as history')
                ->leftJoin(IdentityDatabaseTable::USERS.' as actors', 'history.actor_user_id', '=', 'actors.id')
                ->where('history.correction_request_id', $correctionId)
                ->orderByDesc('history.occurred_at')
                ->get([
                    'history.public_id',
                    'history.action',
                    'actors.name as actor_name',
                    'actors.email as actor_email',
                    'history.reason',
                    'history.occurred_at',
                ])
                ->all(),
        ));
    }

    private function breakExcessSeconds(object $record): int
    {
        if ((bool) ($record->requires_manager_review ?? false) || ! $this->isRegularBreakClosure($this->stringValue($record->closure_reason ?? null))) {
            return 0;
        }

        $userId = $this->intValue($record->user_id ?? null);
        $teamId = $this->intValue($record->team_id ?? null);
        $startedAtValue = $this->stringValue($record->started_at ?? null);
        $endedAtValue = $this->stringValue($record->ended_at ?? null);

        if ($userId <= 0 || $teamId <= 0 || $startedAtValue === '' || $endedAtValue === '') {
            return 0;
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $startedAt = new DateTimeImmutable($startedAtValue, $timezone);
        $endedAt = new DateTimeImmutable($endedAtValue, $timezone);
        $dates = [];

        foreach ((new CalendarDayIntervalSplitter)->split($startedAt, $endedAt) as $slice) {
            $dates[$slice->calendarDate] = true;
        }

        if ($dates === []) {
            return 0;
        }

        $windowStart = new DateTimeImmutable(min(array_keys($dates)).' 00:00:00', $timezone);
        $windowEnd = new DateTimeImmutable(max(array_keys($dates)).' 23:59:59', $timezone);
        $totalsByDate = array_fill_keys(array_keys($dates), 0);

        foreach ($this->database->table(TimeTrackingDatabaseTable::BREAKS)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where('requires_manager_review', false)
            ->whereNotNull('ended_at')
            ->where('started_at', '<=', $windowEnd)
            ->where('ended_at', '>=', $windowStart)
            ->get(['id', 'started_at', 'ended_at', 'exact_seconds', 'closure_reason']) as $row) {
            if (! $this->isRegularBreakClosure($this->stringValue($row->closure_reason ?? null))) {
                continue;
            }

            $rowStartedAt = new DateTimeImmutable($this->stringValue($row->started_at ?? null), $timezone);
            $rowEndedAt = new DateTimeImmutable($this->stringValue($row->ended_at ?? null), $timezone);
            $correctedSeconds = $this->correctedSourceSeconds(CorrectionSourceType::Break, $this->intValue($row->id ?? null), $this->intValue($row->exact_seconds ?? null));
            $rawSeconds = max(1, $rowEndedAt->getTimestamp() - $rowStartedAt->getTimestamp());

            foreach ((new CalendarDayIntervalSplitter)->split($rowStartedAt, $rowEndedAt) as $slice) {
                if (array_key_exists($slice->calendarDate, $totalsByDate)) {
                    $totalsByDate[$slice->calendarDate] += (int) floor($slice->seconds * ($correctedSeconds / $rawSeconds));
                }
            }
        }

        $dailyLimitSeconds = $this->breakPolicies->policyForUserTeam($userId, $teamId)->dailyLimitSeconds;

        return array_sum(array_map(static fn (int $seconds): int => max(0, $seconds - $dailyLimitSeconds), $totalsByDate));
    }

    private function correctedSourceSeconds(CorrectionSourceType $sourceType, int $sourceId, int $fallbackSeconds): int
    {
        $seconds = $this->database->table(TimeTrackingDatabaseTable::CORRECTION_REQUESTS.' as requests')
            ->join(TimeTrackingDatabaseTable::CORRECTION_PROPOSALS.' as proposals', 'proposals.correction_request_id', '=', 'requests.id')
            ->where('requests.source_type', $sourceType->value)
            ->where('requests.source_id', $sourceId)
            ->where('requests.status', 'corrected')
            ->whereNotNull('proposals.final_exact_seconds')
            ->orderByDesc('requests.decided_at')
            ->orderByDesc('requests.id')
            ->value('proposals.final_exact_seconds');

        return is_numeric($seconds) ? (int) $seconds : $fallbackSeconds;
    }

    private function isRegularBreakClosure(string $reason): bool
    {
        return $reason === '' || $reason === 'normal' || $reason === 'user_returned';
    }

    /**
     * @return list<string>
     */
    private function correctionActions(string $status): array
    {
        if ($status !== 'pending') {
            return [];
        }

        return ['reject', 'correct'];
    }

    /**
     * @return array{title: string, rows: list<array<string, string>>}
     */
    private function auditSection(string $aggregatePublicId): array
    {
        return $this->section('pages.time_tracking.admin_detail.sections.audit', array_values(array_map(
            fn (object $row): array => $this->row($row),
            $this->database->table(AuditDatabaseTable::AUDIT_EVENTS)
                ->where('module', 'time_tracking')
                ->where(static function (Builder $query) use ($aggregatePublicId): void {
                    $query
                        ->where('aggregate_public_id', $aggregatePublicId)
                        ->orWhere('target_public_id', $aggregatePublicId);
                })
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get(['public_id', 'occurred_at', 'action', 'result', 'actor_public_id', 'target_public_id', 'reason'])
                ->all(),
        )));
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{title: string, rows: list<array<string, string>>}
     */
    private function section(string $title, array $rows): array
    {
        return [
            'title' => $title,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function row(object $row): array
    {
        $values = [];

        foreach ((array) $row as $key => $value) {
            $values[$this->stringValue($key)] = $this->displayValue($value);
        }

        return $values;
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_THROW_ON_ERROR);
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
