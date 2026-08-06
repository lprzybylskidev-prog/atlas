<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Optional\TimeTracking\Application\BreakSessionCoordinator;
use App\Modules\Optional\TimeTracking\Application\Contracts\BreakPolicyStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\OtherWorkCategoryStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\WorkSessionStore;
use App\Modules\Optional\TimeTracking\Application\CorrectionRequestCoordinator;
use App\Modules\Optional\TimeTracking\Application\DTOs\ExactTimeChange;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionSourceType;
use App\Modules\Optional\TimeTracking\Application\Enums\OtherWorkApprovalStatus;
use App\Modules\Optional\TimeTracking\Application\Enums\WorkSessionClosureReason;
use App\Modules\Optional\TimeTracking\Application\OtherWorkSessionCoordinator;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingAudit;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Modules\Optional\TimeTracking\Domain\Time\CalendarDayIntervalSplitter;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AdminTimeTrackingOperationActionController
{
    private const TIMEZONE = 'Europe/Warsaw';

    private const MANUAL_CONTAINER_CLOSURE_REASON = 'manual_entry_container';

    public function __construct(
        private WorkSessionStore $workSessions,
        private BreakSessionCoordinator $breaks,
        private OtherWorkSessionCoordinator $otherWork,
        private CorrectionRequestCoordinator $corrections,
        private OtherWorkCategoryStore $categories,
        private BreakPolicyStore $breakPolicies,
        private UserTimeReportService $reports,
        private UserSessionRegistry $sessions,
        private TimeTrackingAudit $audit,
        private NotificationPublisher $notifications,
        private ManagerHierarchy $hierarchy,
    ) {}

    public function terminateWorkSession(Request $request, string $session): RedirectResponse
    {
        $reason = $this->reason($request);
        $record = $this->recordByPublicId(DatabaseTable::TIME_TRACKING_WORK_SESSIONS, $session, [
            'id',
            'public_id',
            'user_id',
            'team_id',
            'laravel_session_id',
            'ended_at',
        ]);

        $this->assertRecordForAuthorizedSurface($request, $record);

        if ($this->stringValue($record->ended_at ?? null) !== '') {
            throw ValidationException::withMessages([
                'session' => __('validation.time_tracking.admin_session_already_closed'),
            ]);
        }

        $endedAt = $this->now();
        $closed = $this->workSessions->closeSession(
            $this->intValue($record->id ?? null),
            WorkSessionClosureReason::AdministrativeTermination,
            $endedAt,
        );

        if (! $closed) {
            throw ValidationException::withMessages([
                'session' => __('validation.time_tracking.admin_session_already_closed'),
            ]);
        }

        $laravelSessionId = $this->stringValue($record->laravel_session_id ?? null);

        if ($laravelSessionId !== '') {
            $this->sessions->terminate($laravelSessionId);
        }

        $this->recordAdminAction(
            $request,
            'time_tracking.admin_work_session_terminated',
            $record,
            'time_tracking_work_session',
            $this->stringValue($record->public_id ?? null),
            $reason,
            ['ended_at' => $endedAt->format(DateTimeImmutable::ATOM)],
        );
        $this->notifyTarget($record, 'notifications.time_tracking.admin_action.work_session_terminated.title', 'notifications.time_tracking.admin_action.work_session_terminated.body');

        return back()->with('flash.messages', [FlashMessage::success('flash.time_tracking.admin_work_session_terminated')]);
    }

    public function forceCloseBreak(Request $request, string $break): RedirectResponse
    {
        $reason = $this->reason($request);
        $record = $this->recordByPublicId(DatabaseTable::TIME_TRACKING_BREAKS, $break, ['id', 'public_id', 'user_id', 'team_id', 'ended_at']);

        $this->assertRecordForAuthorizedSurface($request, $record);

        if ($this->stringValue($record->ended_at ?? null) !== '') {
            throw ValidationException::withMessages([
                'break' => __('validation.time_tracking.admin_lock_already_closed'),
            ]);
        }

        $this->breaks->forceClose($this->intValue($record->user_id ?? null), $this->now());
        $this->recordAdminAction($request, 'time_tracking.admin_break_force_closed', $record, 'time_tracking_break', $this->stringValue($record->public_id ?? null), $reason);
        $this->notifyTarget($record, 'notifications.time_tracking.admin_action.break_force_closed.title', 'notifications.time_tracking.admin_action.break_force_closed.body');

        return back()->with('flash.messages', [FlashMessage::success('flash.time_tracking.admin_break_force_closed')]);
    }

    public function convertExcessBreak(Request $request, string $break): RedirectResponse
    {
        $values = Validator::make($request->all(), [
            'converted_seconds' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], $this->validationAttributes())->validate();
        $record = $this->recordByPublicId(DatabaseTable::TIME_TRACKING_BREAKS, $break, [
            'id',
            'public_id',
            'user_id',
            'team_id',
            'started_at',
            'ended_at',
            'exact_seconds',
            'closure_reason',
            'requires_manager_review',
        ]);
        $this->assertRecordForAuthorizedSurface($request, $record);

        $endedAtValue = $this->stringValue($record->ended_at ?? null);
        $exactSeconds = $this->correctedSourceSeconds(CorrectionSourceType::Break, $this->intValue($record->id ?? null), $this->intValue($record->exact_seconds ?? null));
        $excessSeconds = $this->breakExcessSeconds($record);
        $convertedSeconds = $this->intValue($values['converted_seconds'] ?? null);

        if ($endedAtValue === '' || $exactSeconds <= 0 || $excessSeconds <= 0 || $convertedSeconds > $excessSeconds || $convertedSeconds > $exactSeconds) {
            throw ValidationException::withMessages([
                'converted_seconds' => __('validation.time_tracking.break_excess_not_convertible'),
            ]);
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $startedAt = new DateTimeImmutable($this->stringValue($record->started_at ?? null), $timezone);
        $endedAt = new DateTimeImmutable($endedAtValue, $timezone);
        $reason = $this->stringValue($values['reason'] ?? null);
        $final = new ExactTimeChange($startedAt, $endedAt, max(0, $exactSeconds - $convertedSeconds));
        $original = new ExactTimeChange($startedAt, $endedAt, $exactSeconds);
        $now = $this->now();

        $correction = $this->corrections->createSourceFinalCorrection(
            actorUserId: $this->actorId($request),
            userId: $this->intValue($record->user_id ?? null),
            teamId: $this->intValue($record->team_id ?? null),
            sourceType: CorrectionSourceType::Break,
            sourceId: $this->intValue($record->id ?? null),
            original: $original,
            final: $final,
            reason: $reason,
            createdAt: $now,
        );
        $this->recordAdminAction($request, 'time_tracking.admin_break_excess_converted', $record, 'time_tracking_break', $this->stringValue($record->public_id ?? null), $reason, [
            'converted_seconds' => $convertedSeconds,
            'final_exact_seconds' => $final->exactSeconds,
            'correction_request_id' => $correction->id,
        ]);
        $this->notifyTarget($record, 'notifications.time_tracking.admin_action.correction_decided.title', 'notifications.time_tracking.admin_action.correction_decided.body');

        return back()->with('flash.messages', [FlashMessage::success('flash.time_tracking.admin_break_excess_converted')]);
    }

    public function forceCloseOtherWork(Request $request, string $otherWork): RedirectResponse
    {
        $reason = $this->reason($request);
        $record = $this->recordByPublicId(DatabaseTable::TIME_TRACKING_OTHER_WORK, $otherWork, ['id', 'public_id', 'user_id', 'team_id', 'ended_at']);

        $this->assertRecordForAuthorizedSurface($request, $record);

        if ($this->stringValue($record->ended_at ?? null) !== '') {
            throw ValidationException::withMessages([
                'other_work' => __('validation.time_tracking.admin_lock_already_closed'),
            ]);
        }

        $this->otherWork->forceClose($this->intValue($record->user_id ?? null), $this->now(), $reason);
        $this->recordAdminAction($request, 'time_tracking.admin_other_work_force_closed', $record, 'time_tracking_other_work', $this->stringValue($record->public_id ?? null), $reason);
        $this->notifyTarget($record, 'notifications.time_tracking.admin_action.other_work_force_closed.title', 'notifications.time_tracking.admin_action.other_work_force_closed.body');

        return back()->with('flash.messages', [FlashMessage::success('flash.time_tracking.admin_other_work_force_closed')]);
    }

    public function decideOtherWork(Request $request, string $otherWork): RedirectResponse
    {
        $values = Validator::make($request->all(), [
            'decision' => ['required', 'string', 'in:approve,reject'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], $this->validationAttributes())->validate();

        $record = $this->recordByPublicId(DatabaseTable::TIME_TRACKING_OTHER_WORK, $otherWork, [
            'id',
            'public_id',
            'user_id',
            'team_id',
            'approval_status',
            'requires_manager_review',
            'ended_at',
        ]);
        $this->assertRecordForAuthorizedSurface($request, $record);

        if ($this->stringValue($record->ended_at ?? null) === '') {
            throw ValidationException::withMessages([
                'decision' => __('validation.time_tracking.admin_other_work_not_decidable'),
            ]);
        }

        $decision = $this->stringValue($values['decision'] ?? null);
        $status = $decision === 'approve' ? OtherWorkApprovalStatus::Approved : OtherWorkApprovalStatus::Rejected;
        $reason = $this->stringValue($values['reason'] ?? null);
        $decided = $this->otherWork->decidePending(
            otherWorkId: $this->intValue($record->id ?? null),
            actorUserId: $this->actorId($request),
            targetUserId: $this->intValue($record->user_id ?? null),
            teamId: $this->intValue($record->team_id ?? null),
            publicId: $this->stringValue($record->public_id ?? null),
            status: $status,
            reason: $reason,
            decidedAt: $this->now(),
        );

        if (! $decided) {
            throw ValidationException::withMessages([
                'decision' => __('validation.time_tracking.admin_other_work_not_decidable'),
            ]);
        }

        $this->notifyTarget($record, 'notifications.time_tracking.admin_action.other_work_decided.title', 'notifications.time_tracking.admin_action.other_work_decided.body');

        return back()->with('flash.messages', [FlashMessage::success('flash.time_tracking.admin_other_work_decided')]);
    }

    public function decideCorrection(Request $request, string $correction): RedirectResponse
    {
        $values = Validator::make($request->all(), [
            'decision' => ['required', 'string', 'in:reject,correct'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'final_started_at' => ['required_if:decision,correct', 'nullable', 'date'],
            'final_ended_at' => ['required_if:decision,correct', 'nullable', 'date'],
        ], [], $this->validationAttributes())->validate();

        $record = $this->recordByPublicId(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS, $correction, [
            'id',
            'public_id',
            'user_id',
            'team_id',
            'status',
        ]);
        $this->assertRecordForAuthorizedSurface($request, $record);

        $actorId = $this->actorId($request);
        $reason = $this->stringValue($values['reason'] ?? null);
        $now = $this->now();
        $decision = $this->stringValue($values['decision'] ?? null);

        $succeeded = match ($decision) {
            'reject' => $this->corrections->rejectPending($this->intValue($record->id ?? null), $actorId, $reason, $now),
            'correct' => $this->corrections->correctPending($this->intValue($record->id ?? null), $actorId, $this->exactChange($values), $reason, $now),
            default => false,
        };

        if (! $succeeded) {
            throw ValidationException::withMessages([
                'decision' => __('validation.time_tracking.admin_correction_not_pending'),
            ]);
        }

        $this->recordAdminAction($request, 'time_tracking.admin_correction_'.$decision, $record, 'time_tracking_correction_request', $this->stringValue($record->public_id ?? null), $reason);

        $this->notifyTarget($record, 'notifications.time_tracking.admin_action.correction_decided.title', 'notifications.time_tracking.admin_action.correction_decided.body');

        return back()->with('flash.messages', [FlashMessage::success('flash.time_tracking.admin_correction_decided')]);
    }

    public function createManualEntry(Request $request): RedirectResponse
    {
        $values = Validator::make($request->all(), [
            'entry_kind' => ['required', 'string', 'in:work_session,break,other_work'],
            'user_public_id' => ['required', 'string'],
            'team_public_id' => ['required', 'string'],
            'category_key' => ['nullable', 'string', 'max:120'],
            'final_started_at' => ['required', 'date'],
            'final_ended_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], $this->validationAttributes())->validate();

        $team = $this->teamByPublicId($this->stringValue($values['team_public_id'] ?? null));
        $user = $this->userByPublicId($this->stringValue($values['user_public_id'] ?? null));
        $teamId = $this->intValue(data_get($team, 'id'));
        $userId = $this->intValue(data_get($user, 'id'));

        $this->assertRecordForAuthorizedSurface($request, (object) ['team_id' => $teamId, 'user_id' => $userId]);

        $reason = $this->stringValue($values['reason'] ?? null);
        $entryKind = $this->stringValue($values['entry_kind'] ?? null);
        $final = $this->exactChange($values);
        $actorId = $this->actorId($request);
        $manualData = DB::transaction(function () use ($actorId, $entryKind, $final, $reason, $teamId, $userId, $values): array {
            $source = $this->createManualSourceRecord(
                entryKind: $entryKind,
                userId: $userId,
                teamId: $teamId,
                final: $final,
                reason: $reason,
                categoryKey: $this->nullableString($values['category_key'] ?? null),
            );

            return [
                'source' => $source,
                'manual' => $this->corrections->createManualEntry(
                    $actorId,
                    $userId,
                    $teamId,
                    $final,
                    $reason,
                    $this->now(),
                    $source['type'],
                    $source['id'],
                ),
            ];
        });
        $source = $manualData['source'];
        $manual = $manualData['manual'];

        $this->recordAdminAction($request, 'time_tracking.admin_manual_entry_created', (object) ['user_id' => $userId, 'team_id' => $teamId], 'time_tracking_correction_request', $manual->publicId, $reason, [
            'entry_kind' => $entryKind,
            'source_type' => $source['type']->value,
            'source_id' => $source['id'],
        ]);
        $this->notifyTarget((object) ['user_id' => $userId, 'team_id' => $teamId], 'notifications.time_tracking.admin_action.manual_entry_created.title', 'notifications.time_tracking.admin_action.manual_entry_created.body');

        return back()->with('flash.messages', [FlashMessage::success('flash.time_tracking.admin_manual_entry_created')]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $values = Validator::make($request->all(), [
            'team_public_id' => ['required', 'string'],
            'category_key' => ['required', 'string', 'regex:/\A[a-z][a-z0-9_]{1,63}\z/'],
            'label_pl' => ['required', 'string', 'max:255'],
            'label_en' => ['required', 'string', 'max:255'],
            'description_pl' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'requires_comment' => ['boolean'],
            'auto_approval_enabled' => ['boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], $this->validationAttributes())->validate();

        $team = $this->teamByPublicId($this->stringValue($values['team_public_id'] ?? null));
        $teamId = $this->intValue(data_get($team, 'id'));
        $this->assertRecordForAuthorizedSurface($request, (object) ['team_id' => $teamId]);

        $this->categories->upsertTeam(
            $teamId,
            $this->stringValue($values['category_key'] ?? null),
            $this->stringValue($values['label_pl'] ?? null),
            $this->stringValue($values['label_en'] ?? null),
            $this->nullableString($values['description_pl'] ?? null),
            $this->nullableString($values['description_en'] ?? null),
            (bool) ($values['requires_comment'] ?? false),
            (bool) ($values['auto_approval_enabled'] ?? false),
        );

        $this->recordAdminAction($request, 'time_tracking.admin_other_work_category_saved', (object) ['team_id' => $teamId], 'time_tracking_other_work_category', $this->stringValue($values['category_key'] ?? null), $this->stringValue($values['reason'] ?? null));

        return redirect()
            ->route('admin.work-time.other-work.categories.index', [
                'team' => $this->stringValue($values['team_public_id'] ?? null),
                'status' => 'active',
            ])
            ->with('flash.messages', [FlashMessage::success('flash.time_tracking.admin_other_work_category_saved')]);
    }

    public function deactivateCategory(Request $request, string $category): RedirectResponse
    {
        $values = Validator::make($request->all(), [
            'team_public_id' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], $this->validationAttributes())->validate();

        $team = $this->teamByPublicId($this->stringValue($values['team_public_id'] ?? null));
        $teamId = $this->intValue(data_get($team, 'id'));
        $this->assertRecordForAuthorizedSurface($request, (object) ['team_id' => $teamId]);
        $this->categories->deactivateTeam($teamId, $category);
        $this->recordAdminAction($request, 'time_tracking.admin_other_work_category_deactivated', (object) ['team_id' => $teamId], 'time_tracking_other_work_category', $category, $this->stringValue($values['reason'] ?? null));

        return back()->with('flash.messages', [FlashMessage::success('flash.time_tracking.admin_other_work_category_deactivated')]);
    }

    /**
     * @param  list<string>  $columns
     */
    private function recordByPublicId(string $table, string $publicId, array $columns): object
    {
        $record = DB::table($table)->where('public_id', $publicId)->first($columns);

        if (! is_object($record)) {
            abort(404);
        }

        return $record;
    }

    private function teamByPublicId(string $publicId): object
    {
        $team = DB::table(DatabaseTable::TEAMS)->where('public_id', $publicId)->first(['id', 'public_id']);

        if (! is_object($team)) {
            abort(404);
        }

        return $team;
    }

    private function userByPublicId(string $publicId): object
    {
        $user = DB::table(DatabaseTable::USERS)->where('public_id', $publicId)->first(['id', 'public_id']);

        if (! is_object($user)) {
            abort(404);
        }

        return $user;
    }

    private function assertRecordForAuthorizedSurface(Request $request, object $record): void
    {
        $teamId = $this->intValue($record->team_id ?? null);

        if ($this->isManagerRoute($request)) {
            $this->assertRecordForManagerScope($request, $record);

            return;
        }

        if ($teamId < 1 || ! in_array($teamId, $this->adminTeamIds(), true)) {
            throw ValidationException::withMessages([
                'team_public_id' => __('validation.time_tracking.admin_record_team_mismatch'),
            ]);
        }
    }

    private function assertRecordForManagerScope(Request $request, object $record): void
    {
        $teamPublicId = $this->publicId(DatabaseTable::TEAMS, $this->intValue($record->team_id ?? null));
        $targetUserPublicId = $this->publicId(DatabaseTable::USERS, $this->intValue($record->user_id ?? null));
        $managerUserPublicId = data_get($request->user(), 'public_id');

        if (! is_string($managerUserPublicId) || $teamPublicId === null) {
            throw ValidationException::withMessages([
                'team_public_id' => __('validation.time_tracking.admin_record_team_mismatch'),
            ]);
        }

        $scope = $this->hierarchy->scopeFor($teamPublicId, $managerUserPublicId);

        if ($targetUserPublicId === null) {
            if ($scope->visibleUserPublicIds !== []) {
                return;
            }

            throw ValidationException::withMessages([
                'team_public_id' => __('validation.time_tracking.admin_record_team_mismatch'),
            ]);
        }

        if (! in_array($targetUserPublicId, $scope->visibleUserPublicIds, true)) {
            throw ValidationException::withMessages([
                'team_public_id' => __('validation.time_tracking.admin_record_team_mismatch'),
            ]);
        }
    }

    private function isManagerRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) && str_starts_with($routeName, 'manager.work-time.');
    }

    /**
     * @return list<int>
     */
    private function adminTeamIds(): array
    {
        $publicIds = array_values(array_filter(array_map(
            fn (array $team): string => $this->stringValue($team['publicId']),
            $this->reports->adminTeamOptions(),
        )));

        if ($publicIds === []) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $id): int => $this->intValue($id),
            DB::table(DatabaseTable::TEAMS)
                ->whereIn('public_id', $publicIds)
                ->pluck('id')
                ->all(),
        ));
    }

    private function reason(Request $request): string
    {
        $values = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], $this->validationAttributes())->validate();

        return $this->stringValue($values['reason'] ?? null);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function exactChange(array $values): ExactTimeChange
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $startedAt = new DateTimeImmutable($this->stringValue($values['final_started_at'] ?? null), $timezone);
        $endedAt = new DateTimeImmutable($this->stringValue($values['final_ended_at'] ?? null), $timezone);

        if ($endedAt <= $startedAt) {
            throw ValidationException::withMessages([
                'final_ended_at' => __('validation.time_tracking.end_must_be_after_start'),
            ]);
        }

        return new ExactTimeChange(
            startedAt: $startedAt,
            endedAt: $endedAt,
            exactSeconds: $endedAt->getTimestamp() - $startedAt->getTimestamp(),
        );
    }

    /**
     * @return array{type: CorrectionSourceType, id: int}
     */
    private function createManualSourceRecord(string $entryKind, int $userId, int $teamId, ExactTimeChange $final, string $reason, ?string $categoryKey): array
    {
        if ($final->startedAt === null || $final->endedAt === null || $final->exactSeconds === null) {
            throw ValidationException::withMessages([
                'final_started_at' => __('validation.required', ['attribute' => __('validation.attributes.time_tracking_final_started_at')]),
            ]);
        }

        if ($entryKind === CorrectionSourceType::WorkSession->value) {
            $id = DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insertGetId([
                'public_id' => (string) Str::ulid(),
                'user_id' => $userId,
                'team_id' => $teamId,
                'laravel_session_id' => 'admin-manual-'.Str::lower((string) Str::ulid()),
                'started_at' => $final->startedAt,
                'ended_at' => $final->endedAt,
                'exact_seconds' => $final->exactSeconds,
                'closure_reason' => 'manual_entry',
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);

            return ['type' => CorrectionSourceType::WorkSession, 'id' => (int) $id];
        }

        $workSessionId = $this->createManualContainerWorkSession($userId, $teamId, $final);

        if ($entryKind === CorrectionSourceType::Break->value) {
            $id = DB::table(DatabaseTable::TIME_TRACKING_BREAKS)->insertGetId([
                'public_id' => (string) Str::ulid(),
                'work_session_id' => $workSessionId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'started_at' => $final->startedAt,
                'ended_at' => $final->endedAt,
                'exact_seconds' => $final->exactSeconds,
                'closure_reason' => 'normal',
                'requires_manager_review' => false,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);

            return ['type' => CorrectionSourceType::Break, 'id' => (int) $id];
        }

        if ($entryKind !== CorrectionSourceType::OtherWork->value) {
            throw ValidationException::withMessages([
                'entry_kind' => __('validation.in', ['attribute' => __('validation.attributes.time_tracking_entry_kind')]),
            ]);
        }

        if ($categoryKey !== null && ! $this->categoryExistsForTeam($teamId, $categoryKey)) {
            throw ValidationException::withMessages([
                'category_key' => __('validation.exists', ['attribute' => __('validation.attributes.time_tracking_other_work_category')]),
            ]);
        }

        $id = DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $userId,
            'team_id' => $teamId,
            'category_key' => $categoryKey,
            'description' => $reason,
            'end_note' => null,
            'approval_status' => OtherWorkApprovalStatus::Approved->value,
            'started_at' => $final->startedAt,
            'ended_at' => $final->endedAt,
            'exact_seconds' => $final->exactSeconds,
            'closure_reason' => 'normal',
            'requires_manager_review' => false,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);

        return ['type' => CorrectionSourceType::OtherWork, 'id' => (int) $id];
    }

    private function createManualContainerWorkSession(int $userId, int $teamId, ExactTimeChange $final): int
    {
        return (int) DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $userId,
            'team_id' => $teamId,
            'laravel_session_id' => 'admin-manual-container-'.Str::lower((string) Str::ulid()),
            'started_at' => $final->startedAt,
            'ended_at' => $final->startedAt,
            'exact_seconds' => 0,
            'closure_reason' => self::MANUAL_CONTAINER_CLOSURE_REASON,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
    }

    private function categoryExistsForTeam(int $teamId, string $categoryKey): bool
    {
        return DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK_CATEGORIES)
            ->where('scope_type', 'team')
            ->where('scope_id', $teamId)
            ->where('category_key', $categoryKey)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'decision' => __('validation.attributes.time_tracking_admin_decision'),
            'entry_kind' => __('validation.attributes.time_tracking_entry_kind'),
            'reason' => __('validation.attributes.reason'),
            'team_public_id' => __('validation.attributes.team_public_id'),
            'user_public_id' => __('validation.attributes.user_public_id'),
            'category_key' => __('validation.attributes.time_tracking_other_work_category'),
            'label_pl' => __('validation.attributes.time_tracking_other_work_label_pl'),
            'label_en' => __('validation.attributes.time_tracking_other_work_label_en'),
            'final_started_at' => __('validation.attributes.time_tracking_final_started_at'),
            'final_ended_at' => __('validation.attributes.time_tracking_final_ended_at'),
            'converted_seconds' => __('validation.attributes.time_tracking_converted_seconds'),
        ];
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

        foreach (DB::table(DatabaseTable::TIME_TRACKING_BREAKS)
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
        $seconds = DB::table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS.' as requests')
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

    private function isRegularBreakClosure(string $reason): bool
    {
        return $reason === '' || $reason === 'normal' || $reason === 'user_returned';
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function recordAdminAction(
        Request $request,
        string $action,
        object $record,
        string $aggregateType,
        string $aggregatePublicId,
        string $reason,
        array $after = [],
    ): void {
        $this->audit->record(
            action: $action,
            actorUserId: $this->actorId($request),
            targetUserId: $this->intValue($record->user_id ?? null) ?: null,
            teamId: $this->intValue($record->team_id ?? null) ?: null,
            aggregateType: $aggregateType,
            aggregatePublicId: $aggregatePublicId,
            reason: $reason,
            after: $after,
            source: 'http',
        );
    }

    private function notifyTarget(object $record, string $titleKey, string $bodyKey): void
    {
        $userPublicId = $this->publicId(DatabaseTable::USERS, $this->intValue($record->user_id ?? null));
        $teamPublicId = $this->publicId(DatabaseTable::TEAMS, $this->intValue($record->team_id ?? null));

        if ($userPublicId === null || $teamPublicId === null) {
            return;
        }

        $this->notifications->publish(new CreateNotification(
            type: 'time_tracking.admin_action',
            title: $titleKey,
            body: $bodyKey,
            recipientUserPublicId: $userPublicId,
            teamPublicId: $teamPublicId,
            severity: 'warning',
            deepLinkUrl: '/user/notifications',
            data: [
                'title_key' => $titleKey,
                'body_key' => $bodyKey,
            ],
        ));
    }

    private function publicId(string $table, int $id): ?string
    {
        if ($id < 1) {
            return null;
        }

        $publicId = DB::table($table)->where('id', $id)->value('public_id');

        return is_string($publicId) && $publicId !== '' ? $publicId : null;
    }

    private function actorId(Request $request): int
    {
        $id = data_get($request->user(), 'id');

        if (! is_numeric($id)) {
            abort(403);
        }

        return (int) $id;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $string = $this->stringValue($value);

        return trim($string) === '' ? null : $string;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
