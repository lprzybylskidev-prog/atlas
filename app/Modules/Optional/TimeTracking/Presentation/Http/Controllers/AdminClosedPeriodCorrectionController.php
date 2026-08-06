<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\CorrectionRequestCoordinator;
use App\Modules\Optional\TimeTracking\Application\DTOs\ClosedPeriodOverrideAuthorization;
use App\Modules\Optional\TimeTracking\Application\DTOs\ExactTimeChange;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class AdminClosedPeriodCorrectionController
{
    public function __construct(
        private CorrectionRequestCoordinator $corrections,
        private AuditRecorder $audit,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $values = Validator::make($request->all(), [
            'user_public_id' => ['required', 'string'],
            'team_public_id' => ['required', 'string'],
            'original_started_at' => ['required', 'date'],
            'original_ended_at' => ['required', 'date'],
            'original_exact_seconds' => ['required', 'integer', 'min:0'],
            'final_started_at' => ['required', 'date'],
            'final_ended_at' => ['required', 'date'],
            'final_exact_seconds' => ['required', 'integer', 'min:0'],
            'before_after_preview_confirmed' => ['accepted'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], [
            'user_public_id' => __('validation.attributes.user_public_id'),
            'team_public_id' => __('validation.attributes.team_public_id'),
            'original_started_at' => __('validation.attributes.time_tracking_original_started_at'),
            'original_ended_at' => __('validation.attributes.time_tracking_original_ended_at'),
            'original_exact_seconds' => __('validation.attributes.time_tracking_original_exact_seconds'),
            'final_started_at' => __('validation.attributes.time_tracking_final_started_at'),
            'final_ended_at' => __('validation.attributes.time_tracking_final_ended_at'),
            'final_exact_seconds' => __('validation.attributes.time_tracking_final_exact_seconds'),
            'before_after_preview_confirmed' => __('validation.attributes.time_tracking_before_after_preview_confirmed'),
            'reason' => __('validation.attributes.reason'),
        ])->validate();

        $actorUserId = data_get($request->user(), 'id');
        $actorPublicId = data_get($request->user(), 'public_id');
        $activeTeamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $targetUserPublicId = $this->stringValue($values['user_public_id'] ?? null);
        $targetTeamPublicId = $this->stringValue($values['team_public_id'] ?? null);
        $reason = $this->stringValue($values['reason'] ?? null);

        if (! is_numeric($actorUserId) || ! is_string($actorPublicId) || ! is_string($activeTeamPublicId) || $activeTeamPublicId !== $targetTeamPublicId) {
            $this->recordAudit($actorPublicId, $activeTeamPublicId, $targetUserPublicId, null, $reason, 'rejected', [], [], [
                'rejection_reason' => 'invalid_active_team',
            ]);

            throw ValidationException::withMessages([
                'team_public_id' => __('validation.time_tracking.closed_period_active_team'),
            ]);
        }

        $teamId = $this->teamId($targetTeamPublicId);
        $targetUserId = $this->userId($targetUserPublicId);

        if ($teamId === null || $targetUserId === null) {
            $this->recordAudit($actorPublicId, $targetTeamPublicId, $targetUserPublicId, null, $reason, 'rejected', [], [], [
                'rejection_reason' => 'target_not_found',
            ]);

            throw ValidationException::withMessages([
                'user_public_id' => __('validation.exists', ['attribute' => __('validation.attributes.user_public_id')]),
            ]);
        }

        $original = $this->change(
            $values['original_started_at'] ?? null,
            $values['original_ended_at'] ?? null,
            $values['original_exact_seconds'] ?? null,
        );
        $final = $this->change(
            $values['final_started_at'] ?? null,
            $values['final_ended_at'] ?? null,
            $values['final_exact_seconds'] ?? null,
        );

        if ($this->eligibleHeadManagerExists($teamId)) {
            $this->recordAudit($actorPublicId, $targetTeamPublicId, $targetUserPublicId, null, $reason, 'rejected', $this->payload($original), $this->payload($final), [
                'rejection_reason' => 'eligible_head_manager_exists',
            ]);

            throw ValidationException::withMessages([
                'team_public_id' => __('validation.time_tracking.closed_period_head_manager_exists'),
            ]);
        }

        $correction = $this->corrections->createClosedPeriodCorrection(
            actorUserId: (int) $actorUserId,
            userId: $targetUserId,
            teamId: $teamId,
            original: $original,
            final: $final,
            authorization: new ClosedPeriodOverrideAuthorization(
                actorScope: 'admin',
                adminModeConfirmed: true,
                highRiskReauthenticated: true,
                mfaConfirmed: true,
                beforeAfterPreviewConfirmed: true,
                reason: $reason,
                authorizedAt: new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')),
            ),
        );

        $this->recordAudit($actorPublicId, $targetTeamPublicId, $targetUserPublicId, $correction->publicId, $reason, 'succeeded', $this->payload($original), $this->payload($final), [
            'actor_scope' => 'admin',
            'no_eligible_head_manager' => true,
        ]);

        return back()->with('flash.messages', [FlashMessage::success('flash.time_tracking.closed_period_override_created')]);
    }

    private function teamId(string $teamPublicId): ?int
    {
        $id = DB::table(TeamsDatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->where('is_active', true)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function userId(string $userPublicId): ?int
    {
        $id = DB::table(IdentityDatabaseTable::USERS)
            ->where('public_id', $userPublicId)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function eligibleHeadManagerExists(int $teamId): bool
    {
        return DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->where('is_head_manager', true)
            ->where(static function (Builder $query): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>', now());
            })
            ->exists();
    }

    private function change(mixed $startedAt, mixed $endedAt, mixed $exactSeconds): ExactTimeChange
    {
        $timezone = new DateTimeZone('Europe/Warsaw');

        return new ExactTimeChange(
            startedAt: new DateTimeImmutable($this->stringValue($startedAt), $timezone),
            endedAt: new DateTimeImmutable($this->stringValue($endedAt), $timezone),
            exactSeconds: $this->intValue($exactSeconds),
        );
    }

    /**
     * @return array{started_at: ?string, ended_at: ?string, exact_seconds: ?int}
     */
    private function payload(ExactTimeChange $change): array
    {
        return [
            'started_at' => $change->startedAt?->format(DateTimeImmutable::ATOM),
            'ended_at' => $change->endedAt?->format(DateTimeImmutable::ATOM),
            'exact_seconds' => $change->exactSeconds,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    private function recordAudit(
        mixed $actorPublicId,
        mixed $teamPublicId,
        string $targetUserPublicId,
        ?string $correctionPublicId,
        string $reason,
        string $result,
        array $before,
        array $after,
        array $metadata,
    ): void {
        $this->audit->record(new AuditEvent(
            module: 'time_tracking',
            action: $result === 'succeeded' ? 'time_tracking.closed_period_override_created' : 'time_tracking.closed_period_override_rejected',
            result: $result,
            source: 'http',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetType: 'user',
            targetPublicId: $targetUserPublicId,
            aggregateType: $correctionPublicId === null ? null : 'time_tracking_correction_request',
            aggregatePublicId: $correctionPublicId,
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            reason: $reason,
            before: $before,
            after: $after,
            metadata: $metadata,
            security: true,
            securityCategory: SecurityAuditCategory::Security,
        ));
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
