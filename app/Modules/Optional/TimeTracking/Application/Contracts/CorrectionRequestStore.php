<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

use App\Modules\Optional\TimeTracking\Application\DTOs\ClosedPeriodOverrideAuthorization;
use App\Modules\Optional\TimeTracking\Application\DTOs\CorrectionRequest;
use App\Modules\Optional\TimeTracking\Application\DTOs\ExactTimeChange;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionSourceType;
use DateTimeImmutable;

interface CorrectionRequestStore
{
    public function requestDescriptive(
        int $userId,
        int $teamId,
        ?int $workSessionId,
        string $description,
        DateTimeImmutable $requestedAt,
    ): CorrectionRequest;

    public function requestExactChange(
        int $userId,
        int $teamId,
        ?int $workSessionId,
        string $description,
        ExactTimeChange $original,
        ExactTimeChange $proposed,
        DateTimeImmutable $requestedAt,
    ): CorrectionRequest;

    public function requestSourceDescriptive(
        int $userId,
        int $teamId,
        CorrectionSourceType $sourceType,
        int $sourceId,
        string $description,
        DateTimeImmutable $requestedAt,
    ): CorrectionRequest;

    public function requestSourceExactChange(
        int $userId,
        int $teamId,
        CorrectionSourceType $sourceType,
        int $sourceId,
        string $description,
        ExactTimeChange $original,
        ExactTimeChange $proposed,
        DateTimeImmutable $requestedAt,
    ): CorrectionRequest;

    public function cancelPending(int $requestId, int $actorUserId, string $reason, DateTimeImmutable $cancelledAt): bool;

    public function rejectPending(int $requestId, int $managerUserId, string $reason, DateTimeImmutable $decidedAt): bool;

    public function correctPending(int $requestId, int $managerUserId, ExactTimeChange $final, string $reason, DateTimeImmutable $decidedAt): bool;

    public function createManualEntry(
        int $managerUserId,
        int $userId,
        int $teamId,
        ExactTimeChange $final,
        string $reason,
        DateTimeImmutable $createdAt,
        ?CorrectionSourceType $sourceType = null,
        ?int $sourceId = null,
    ): CorrectionRequest;

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
    ): CorrectionRequest;

    public function createClosedPeriodCorrection(
        int $actorUserId,
        int $userId,
        int $teamId,
        ExactTimeChange $original,
        ExactTimeChange $final,
        ClosedPeriodOverrideAuthorization $authorization,
    ): CorrectionRequest;
}
