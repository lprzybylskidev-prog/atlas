<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Domain\Offline;

use DateInterval;

final readonly class OfflineReconciler
{
    public function reconcile(
        OfflineReconciliationRequest $request,
        OfflineReconciliationState $state,
        OfflineReconciliationPolicy $policy,
    ): OfflineReconciliationResult {
        if ($request->sequence === $state->lastAcceptedSequence) {
            return $this->reject(OfflineReconciliationDecision::RejectedDuplicate);
        }

        if ($request->sequence < $state->lastAcceptedSequence) {
            return $this->reject(OfflineReconciliationDecision::RejectedReordered);
        }

        if ($request->workSessionPublicId !== $state->activeWorkSessionPublicId || $state->receivedAt >= $state->sessionExpiresAt) {
            return $this->reject(OfflineReconciliationDecision::RejectedExpiredSession);
        }

        if ($request->deviceLeaseId !== $state->activeDeviceLeaseId) {
            return $this->reject(OfflineReconciliationDecision::RejectedParallelWork);
        }

        if ($request->monotonicElapsedSeconds > $policy->maximumOfflineGapSeconds) {
            return $this->reject(OfflineReconciliationDecision::RejectedExcessiveGap);
        }

        if ($this->hasClockAnomaly($request, $state, $policy)) {
            return $this->reject(OfflineReconciliationDecision::RejectedClockAnomaly);
        }

        if ($request->monotonicElapsedSeconds > $policy->inactivityThresholdSeconds) {
            return new OfflineReconciliationResult(
                decision: OfflineReconciliationDecision::AcceptedEndedByInactivity,
                acceptedSeconds: $policy->inactivityThresholdSeconds,
                countedEndsAt: $request->serverAnchorAt->add(new DateInterval(sprintf('PT%dS', $policy->inactivityThresholdSeconds))),
            );
        }

        return new OfflineReconciliationResult(
            decision: OfflineReconciliationDecision::AcceptedActive,
            acceptedSeconds: $request->monotonicElapsedSeconds,
            countedEndsAt: $request->serverAnchorAt->add(new DateInterval(sprintf('PT%dS', $request->monotonicElapsedSeconds))),
        );
    }

    private function hasClockAnomaly(
        OfflineReconciliationRequest $request,
        OfflineReconciliationState $state,
        OfflineReconciliationPolicy $policy,
    ): bool {
        if ($request->serverAnchorAt > $state->receivedAt) {
            return true;
        }

        $serverElapsedSeconds = $state->receivedAt->getTimestamp() - $request->serverAnchorAt->getTimestamp();

        return abs($serverElapsedSeconds - $request->monotonicElapsedSeconds) > $policy->clockSkewToleranceSeconds;
    }

    private function reject(OfflineReconciliationDecision $decision): OfflineReconciliationResult
    {
        return new OfflineReconciliationResult(
            decision: $decision,
            acceptedSeconds: 0,
            countedEndsAt: null,
        );
    }
}
