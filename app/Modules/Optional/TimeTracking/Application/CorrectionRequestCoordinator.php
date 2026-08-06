<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Optional\TimeTracking\Application\Contracts\CorrectionRequestStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\ClosedPeriodOverrideAuthorization;
use App\Modules\Optional\TimeTracking\Application\DTOs\CorrectionRequest;
use App\Modules\Optional\TimeTracking\Application\DTOs\ExactTimeChange;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionSourceType;
use DateTimeImmutable;

final readonly class CorrectionRequestCoordinator
{
    public function __construct(
        private CorrectionRequestStore $requests,
        private TimeTrackingAudit $audit,
    ) {}

    public function requestDescriptive(int $userId, int $teamId, ?int $workSessionId, string $description, DateTimeImmutable $requestedAt): CorrectionRequest
    {
        $request = $this->requests->requestDescriptive($userId, $teamId, $workSessionId, $description, $requestedAt);
        $this->recordCreated($request, $userId, $teamId, 'descriptive', $requestedAt);

        return $request;
    }

    public function requestExactChange(int $userId, int $teamId, ?int $workSessionId, string $description, ExactTimeChange $original, ExactTimeChange $proposed, DateTimeImmutable $requestedAt): CorrectionRequest
    {
        $request = $this->requests->requestExactChange($userId, $teamId, $workSessionId, $description, $original, $proposed, $requestedAt);
        $this->recordCreated($request, $userId, $teamId, 'exact_change', $requestedAt);

        return $request;
    }

    public function requestSourceDescriptive(int $userId, int $teamId, CorrectionSourceType $sourceType, int $sourceId, string $description, DateTimeImmutable $requestedAt): CorrectionRequest
    {
        $request = $this->requests->requestSourceDescriptive($userId, $teamId, $sourceType, $sourceId, $description, $requestedAt);
        $this->recordCreated($request, $userId, $teamId, 'descriptive', $requestedAt);

        return $request;
    }

    public function requestSourceExactChange(int $userId, int $teamId, CorrectionSourceType $sourceType, int $sourceId, string $description, ExactTimeChange $original, ExactTimeChange $proposed, DateTimeImmutable $requestedAt): CorrectionRequest
    {
        $request = $this->requests->requestSourceExactChange($userId, $teamId, $sourceType, $sourceId, $description, $original, $proposed, $requestedAt);
        $this->recordCreated($request, $userId, $teamId, 'exact_change', $requestedAt);

        return $request;
    }

    public function cancelPending(int $requestId, int $actorUserId, string $reason, DateTimeImmutable $cancelledAt): bool
    {
        $cancelled = $this->requests->cancelPending($requestId, $actorUserId, $reason, $cancelledAt);
        $this->recordDecision($cancelled, 'time_tracking.correction_cancelled', $requestId, $actorUserId, $reason, $cancelledAt);

        return $cancelled;
    }

    public function rejectPending(int $requestId, int $managerUserId, string $reason, DateTimeImmutable $decidedAt): bool
    {
        $rejected = $this->requests->rejectPending($requestId, $managerUserId, $reason, $decidedAt);
        $this->recordDecision($rejected, 'time_tracking.correction_rejected', $requestId, $managerUserId, $reason, $decidedAt);

        return $rejected;
    }

    public function correctPending(int $requestId, int $managerUserId, ExactTimeChange $final, string $reason, DateTimeImmutable $decidedAt): bool
    {
        $corrected = $this->requests->correctPending($requestId, $managerUserId, $final, $reason, $decidedAt);
        $this->recordDecision($corrected, 'time_tracking.correction_corrected', $requestId, $managerUserId, $reason, $decidedAt);

        return $corrected;
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
        $request = $this->requests->createManualEntry($managerUserId, $userId, $teamId, $final, $reason, $createdAt, $sourceType, $sourceId);
        $this->audit->record(
            action: 'time_tracking.manual_entry_created',
            actorUserId: $managerUserId,
            targetUserId: $userId,
            teamId: $teamId,
            aggregateType: 'time_tracking_correction_request',
            aggregatePublicId: $request->publicId,
            reason: $reason,
            after: [
                'created_at' => $createdAt->format(DateTimeImmutable::ATOM),
                'source_type' => $sourceType?->value,
                'source_id' => $sourceId,
            ],
        );

        return $request;
    }

    public function createSourceFinalCorrection(int $actorUserId, int $userId, int $teamId, CorrectionSourceType $sourceType, int $sourceId, ExactTimeChange $original, ExactTimeChange $final, string $reason, DateTimeImmutable $createdAt): CorrectionRequest
    {
        $request = $this->requests->createSourceFinalCorrection($actorUserId, $userId, $teamId, $sourceType, $sourceId, $original, $final, $reason, $createdAt);
        $this->recordDecision(true, 'time_tracking.source_final_correction_created', $request->id, $actorUserId, $reason, $createdAt);

        return $request;
    }

    public function createClosedPeriodCorrection(
        int $actorUserId,
        int $userId,
        int $teamId,
        ExactTimeChange $original,
        ExactTimeChange $final,
        ClosedPeriodOverrideAuthorization $authorization,
    ): CorrectionRequest {
        $request = $this->requests->createClosedPeriodCorrection($actorUserId, $userId, $teamId, $original, $final, $authorization);
        $this->audit->record(
            action: 'time_tracking.closed_period_correction_created',
            actorUserId: $actorUserId,
            targetUserId: $userId,
            teamId: $teamId,
            aggregateType: 'time_tracking_correction_request',
            aggregatePublicId: $request->publicId,
            reason: $authorization->reason,
            after: [
                'authorized_at' => $authorization->authorizedAt->format(DateTimeImmutable::ATOM),
                'actor_scope' => $authorization->actorScope,
            ],
        );

        return $request;
    }

    private function recordCreated(CorrectionRequest $request, int $userId, int $teamId, string $type, DateTimeImmutable $requestedAt): void
    {
        $this->audit->record(
            action: 'time_tracking.correction_requested',
            actorUserId: $userId,
            targetUserId: $userId,
            teamId: $teamId,
            aggregateType: 'time_tracking_correction_request',
            aggregatePublicId: $request->publicId,
            after: [
                'request_type' => $type,
                'requested_at' => $requestedAt->format(DateTimeImmutable::ATOM),
            ],
        );
    }

    private function recordDecision(bool $succeeded, string $action, int $requestId, int $actorUserId, string $reason, DateTimeImmutable $occurredAt): void
    {
        if (! $succeeded) {
            return;
        }

        $this->audit->recordCorrectionRequest(
            action: $action,
            requestId: $requestId,
            actorUserId: $actorUserId,
            reason: $reason,
            after: ['occurred_at' => $occurredAt->format(DateTimeImmutable::ATOM)],
        );
    }
}
