<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\CorrectionRequestStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\ClosedPeriodOverrideAuthorization;
use App\Modules\Optional\TimeTracking\Application\DTOs\CorrectionRequest;
use App\Modules\Optional\TimeTracking\Application\DTOs\ExactTimeChange;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionHistoryAction;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionRequestStatus;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionRequestType;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionSourceType;
use App\Shared\Infrastructure\Database\DatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DatabaseCorrectionRequestStore implements CorrectionRequestStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function requestDescriptive(
        int $userId,
        int $teamId,
        ?int $workSessionId,
        string $description,
        DateTimeImmutable $requestedAt,
    ): CorrectionRequest {
        return $this->createRequest(
            userId: $userId,
            teamId: $teamId,
            workSessionId: $workSessionId,
            sourceType: $workSessionId === null ? null : CorrectionSourceType::WorkSession,
            sourceId: $workSessionId,
            type: CorrectionRequestType::Descriptive,
            description: $description,
            requestedAt: $requestedAt,
            actorUserId: $userId,
            original: null,
            proposed: null,
            final: null,
            historyAction: CorrectionHistoryAction::Requested,
        );
    }

    public function requestExactChange(
        int $userId,
        int $teamId,
        ?int $workSessionId,
        string $description,
        ExactTimeChange $original,
        ExactTimeChange $proposed,
        DateTimeImmutable $requestedAt,
    ): CorrectionRequest {
        return $this->createRequest(
            userId: $userId,
            teamId: $teamId,
            workSessionId: $workSessionId,
            sourceType: $workSessionId === null ? null : CorrectionSourceType::WorkSession,
            sourceId: $workSessionId,
            type: CorrectionRequestType::ExactChange,
            description: $description,
            requestedAt: $requestedAt,
            actorUserId: $userId,
            original: $original,
            proposed: $proposed,
            final: null,
            historyAction: CorrectionHistoryAction::Requested,
        );
    }

    public function requestSourceDescriptive(
        int $userId,
        int $teamId,
        CorrectionSourceType $sourceType,
        int $sourceId,
        string $description,
        DateTimeImmutable $requestedAt,
    ): CorrectionRequest {
        return $this->createRequest(
            userId: $userId,
            teamId: $teamId,
            workSessionId: $sourceType === CorrectionSourceType::WorkSession ? $sourceId : null,
            sourceType: $sourceType,
            sourceId: $sourceId,
            type: CorrectionRequestType::Descriptive,
            description: $description,
            requestedAt: $requestedAt,
            actorUserId: $userId,
            original: null,
            proposed: null,
            final: null,
            historyAction: CorrectionHistoryAction::Requested,
        );
    }

    public function requestSourceExactChange(
        int $userId,
        int $teamId,
        CorrectionSourceType $sourceType,
        int $sourceId,
        string $description,
        ExactTimeChange $original,
        ExactTimeChange $proposed,
        DateTimeImmutable $requestedAt,
    ): CorrectionRequest {
        return $this->createRequest(
            userId: $userId,
            teamId: $teamId,
            workSessionId: $sourceType === CorrectionSourceType::WorkSession ? $sourceId : null,
            sourceType: $sourceType,
            sourceId: $sourceId,
            type: CorrectionRequestType::ExactChange,
            description: $description,
            requestedAt: $requestedAt,
            actorUserId: $userId,
            original: $original,
            proposed: $proposed,
            final: null,
            historyAction: CorrectionHistoryAction::Requested,
        );
    }

    public function cancelPending(int $requestId, int $actorUserId, string $reason, DateTimeImmutable $cancelledAt): bool
    {
        return $this->decide(
            requestId: $requestId,
            actorUserId: $actorUserId,
            status: CorrectionRequestStatus::Cancelled,
            action: CorrectionHistoryAction::Cancelled,
            reason: $reason,
            decidedAt: $cancelledAt,
            final: null,
        );
    }

    public function rejectPending(int $requestId, int $managerUserId, string $reason, DateTimeImmutable $decidedAt): bool
    {
        return $this->decide($requestId, $managerUserId, CorrectionRequestStatus::Rejected, CorrectionHistoryAction::Rejected, $reason, $decidedAt, null);
    }

    public function correctPending(int $requestId, int $managerUserId, ExactTimeChange $final, string $reason, DateTimeImmutable $decidedAt): bool
    {
        return $this->decide($requestId, $managerUserId, CorrectionRequestStatus::Corrected, CorrectionHistoryAction::Corrected, $reason, $decidedAt, $final);
    }

    public function createManualEntry(
        int $managerUserId,
        int $userId,
        int $teamId,
        ExactTimeChange $final,
        string $reason,
        DateTimeImmutable $createdAt,
        ?CorrectionSourceType $sourceType = null,
        ?int $sourceId = null,
    ): CorrectionRequest {
        return $this->createRequest(
            userId: $userId,
            teamId: $teamId,
            workSessionId: $sourceType === CorrectionSourceType::WorkSession ? $sourceId : null,
            sourceType: $sourceType,
            sourceId: $sourceId,
            type: CorrectionRequestType::ManualEntry,
            description: $reason,
            requestedAt: $createdAt,
            actorUserId: $managerUserId,
            original: null,
            proposed: null,
            final: $final,
            historyAction: CorrectionHistoryAction::ManualEntry,
            status: CorrectionRequestStatus::Corrected,
            decidedAt: $createdAt,
            decidedByUserId: $managerUserId,
            decisionReason: $reason,
        );
    }

    public function createSourceFinalCorrection(
        int $actorUserId,
        int $userId,
        int $teamId,
        CorrectionSourceType $sourceType,
        int $sourceId,
        ExactTimeChange $original,
        ExactTimeChange $final,
        string $reason,
        DateTimeImmutable $createdAt,
    ): CorrectionRequest {
        return $this->createRequest(
            userId: $userId,
            teamId: $teamId,
            workSessionId: $sourceType === CorrectionSourceType::WorkSession ? $sourceId : null,
            sourceType: $sourceType,
            sourceId: $sourceId,
            type: CorrectionRequestType::ExactChange,
            description: $reason,
            requestedAt: $createdAt,
            actorUserId: $actorUserId,
            original: $original,
            proposed: null,
            final: $final,
            historyAction: CorrectionHistoryAction::Corrected,
            status: CorrectionRequestStatus::Corrected,
            decidedAt: $createdAt,
            decidedByUserId: $actorUserId,
            decisionReason: $reason,
        );
    }

    public function createClosedPeriodCorrection(
        int $actorUserId,
        int $userId,
        int $teamId,
        ExactTimeChange $original,
        ExactTimeChange $final,
        ClosedPeriodOverrideAuthorization $authorization,
    ): CorrectionRequest {
        if (
            ! $authorization->highRiskReauthenticated
            || ! $authorization->mfaConfirmed
            || ! $authorization->beforeAfterPreviewConfirmed
            || ($authorization->actorScope === 'admin' && ! $authorization->adminModeConfirmed)
        ) {
            throw new InvalidArgumentException('Closed-period correction requires Admin mode when applicable, high-risk reauthentication, MFA, before/after preview, and reason.');
        }

        return $this->database->transaction(function () use ($actorUserId, $userId, $teamId, $original, $final, $authorization): CorrectionRequest {
            $request = $this->createRequest(
                userId: $userId,
                teamId: $teamId,
                workSessionId: null,
                sourceType: null,
                sourceId: null,
                type: CorrectionRequestType::ClosedPeriodOverride,
                description: $authorization->reason,
                requestedAt: $authorization->authorizedAt,
                actorUserId: $actorUserId,
                original: $original,
                proposed: null,
                final: $final,
                historyAction: CorrectionHistoryAction::ClosedPeriodOverride,
                status: CorrectionRequestStatus::Corrected,
                decidedAt: $authorization->authorizedAt,
                decidedByUserId: $actorUserId,
                decisionReason: $authorization->reason,
            );
            $now = now();

            $this->database->table(DatabaseTable::TIME_TRACKING_CLOSED_PERIOD_OVERRIDES)->insert([
                'public_id' => (string) Str::ulid(),
                'correction_request_id' => $request->id,
                'actor_user_id' => $actorUserId,
                'actor_scope' => $authorization->actorScope,
                'admin_mode_confirmed' => $authorization->adminModeConfirmed,
                'high_risk_reauthenticated' => $authorization->highRiskReauthenticated,
                'mfa_confirmed' => $authorization->mfaConfirmed,
                'before_after_preview_confirmed' => $authorization->beforeAfterPreviewConfirmed,
                'reason' => $authorization->reason,
                'authorized_at' => $authorization->authorizedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $request;
        });
    }

    private function createRequest(
        int $userId,
        int $teamId,
        ?int $workSessionId,
        ?CorrectionSourceType $sourceType,
        ?int $sourceId,
        CorrectionRequestType $type,
        string $description,
        DateTimeImmutable $requestedAt,
        int $actorUserId,
        ?ExactTimeChange $original,
        ?ExactTimeChange $proposed,
        ?ExactTimeChange $final,
        CorrectionHistoryAction $historyAction,
        CorrectionRequestStatus $status = CorrectionRequestStatus::Pending,
        ?DateTimeImmutable $decidedAt = null,
        ?int $decidedByUserId = null,
        ?string $decisionReason = null,
    ): CorrectionRequest {
        $this->assertReason($description, 'Correction description');

        return $this->database->transaction(function () use ($userId, $teamId, $workSessionId, $sourceType, $sourceId, $type, $description, $requestedAt, $actorUserId, $original, $proposed, $final, $historyAction, $status, $decidedAt, $decidedByUserId, $decisionReason): CorrectionRequest {
            $now = now();
            $publicId = (string) Str::ulid();
            $requestId = (int) $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)->insertGetId([
                'public_id' => $publicId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'work_session_id' => $workSessionId,
                'source_type' => $sourceType?->value,
                'source_id' => $sourceId,
                'status' => $status->value,
                'request_type' => $type->value,
                'description' => $description,
                'requested_at' => $requestedAt,
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
                'cancellation_reason' => null,
                'decided_at' => $decidedAt,
                'decided_by_user_id' => $decidedByUserId,
                'decision_reason' => $decisionReason,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($original !== null || $proposed !== null || $final !== null) {
                $this->storeProposal($requestId, $original, $proposed, $final);
            }

            $this->appendHistory($requestId, $actorUserId, $historyAction, $description, $requestedAt, [
                'request_type' => $type->value,
                'source_type' => $sourceType?->value,
                'source_id' => $sourceId,
                'status' => $status->value,
            ]);

            return new CorrectionRequest($requestId, $publicId, $status->value);
        });
    }

    private function decide(
        int $requestId,
        int $actorUserId,
        CorrectionRequestStatus $status,
        CorrectionHistoryAction $action,
        string $reason,
        DateTimeImmutable $decidedAt,
        ?ExactTimeChange $final,
    ): bool {
        $this->assertReason($reason, 'Correction decision reason');

        return $this->database->transaction(function () use ($requestId, $actorUserId, $status, $action, $reason, $decidedAt, $final): bool {
            $request = $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)
                ->where('id', $requestId)
                ->lockForUpdate()
                ->first(['id', 'status']);

            if (! is_object($request) || $this->stringValue($request->status ?? null) !== CorrectionRequestStatus::Pending->value) {
                return false;
            }

            $updates = [
                'status' => $status->value,
                'decided_at' => $decidedAt,
                'decided_by_user_id' => $actorUserId,
                'decision_reason' => $reason,
                'updated_at' => now(),
            ];

            if ($status === CorrectionRequestStatus::Cancelled) {
                $updates['cancelled_at'] = $decidedAt;
                $updates['cancelled_by_user_id'] = $actorUserId;
                $updates['cancellation_reason'] = $reason;
                $updates['decided_at'] = null;
                $updates['decided_by_user_id'] = null;
                $updates['decision_reason'] = null;
            }

            $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)
                ->where('id', $requestId)
                ->where('status', CorrectionRequestStatus::Pending->value)
                ->update($updates);

            if ($final !== null) {
                $this->updateFinalProposal($requestId, $final);
            }

            $this->appendHistory($requestId, $actorUserId, $action, $reason, $decidedAt, [
                'status' => $status->value,
            ]);

            return true;
        });
    }

    private function storeProposal(int $requestId, ?ExactTimeChange $original, ?ExactTimeChange $proposed, ?ExactTimeChange $final): void
    {
        $now = now();

        $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_PROPOSALS)->insert([
            'public_id' => (string) Str::ulid(),
            'correction_request_id' => $requestId,
            'original_started_at' => $original?->startedAt,
            'original_ended_at' => $original?->endedAt,
            'original_exact_seconds' => $original?->exactSeconds,
            'proposed_started_at' => $proposed?->startedAt,
            'proposed_ended_at' => $proposed?->endedAt,
            'proposed_exact_seconds' => $proposed?->exactSeconds,
            'final_started_at' => $final?->startedAt,
            'final_ended_at' => $final?->endedAt,
            'final_exact_seconds' => $final?->exactSeconds,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function updateFinalProposal(int $requestId, ExactTimeChange $final): void
    {
        $existing = $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_PROPOSALS)
            ->where('correction_request_id', $requestId)
            ->exists();

        if (! $existing) {
            $this->storeProposal($requestId, null, null, $final);

            return;
        }

        $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_PROPOSALS)
            ->where('correction_request_id', $requestId)
            ->update([
                'final_started_at' => $final->startedAt,
                'final_ended_at' => $final->endedAt,
                'final_exact_seconds' => $final->exactSeconds,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, bool|int|string|null>  $payload
     */
    private function appendHistory(int $requestId, int $actorUserId, CorrectionHistoryAction $action, string $reason, DateTimeImmutable $occurredAt, array $payload): void
    {
        $now = now();

        $this->database->table(DatabaseTable::TIME_TRACKING_CORRECTION_HISTORY)->insert([
            'public_id' => (string) Str::ulid(),
            'correction_request_id' => $requestId,
            'actor_user_id' => $actorUserId,
            'action' => $action->value,
            'reason' => $reason,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $occurredAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function assertReason(string $reason, string $field): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException($field.' cannot be empty.');
        }
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
