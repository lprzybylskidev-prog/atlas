<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Optional\TimeTracking\Application\Contracts\ActiveTimeLockStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\WorkSessionStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\InactivityDecision;
use App\Modules\Optional\TimeTracking\Application\DTOs\InactivityPolicy;
use App\Modules\Optional\TimeTracking\Application\Enums\WorkSessionClosureReason;
use DateInterval;
use DateTimeImmutable;

final readonly class InactivityCoordinator
{
    public function __construct(
        private WorkSessionStore $workSessions,
        private ActiveTimeLockStore $locks,
        private TimeTrackingAudit $audit,
        private TimeTrackingLiveStatusPublisher $liveStatus,
    ) {}

    public function evaluate(
        int $userId,
        DateTimeImmutable $lastActivityAt,
        DateTimeImmutable $observedAt,
        InactivityPolicy $policy = new InactivityPolicy,
    ): InactivityDecision {
        $warningStartsAt = $lastActivityAt->add(new DateInterval('PT'.$policy->inactivityThresholdSeconds.'S'));
        $warningEndsAt = $warningStartsAt->add(new DateInterval('PT'.$policy->warningSeconds.'S'));

        if ($observedAt < $warningStartsAt || $this->locks->activeForUser($userId) !== null) {
            return new InactivityDecision(false, $warningStartsAt, $warningEndsAt);
        }

        $this->workSessions->closeActiveForUser($userId, WorkSessionClosureReason::Inactivity, $warningStartsAt);
        $this->audit->record(
            action: 'time_tracking.inactivity_logout',
            targetUserId: $userId,
            reason: WorkSessionClosureReason::Inactivity->value,
            after: [
                'last_activity_at' => $lastActivityAt->format(DateTimeImmutable::ATOM),
                'warning_started_at' => $warningStartsAt->format(DateTimeImmutable::ATOM),
                'observed_at' => $observedAt->format(DateTimeImmutable::ATOM),
            ],
        );
        $this->liveStatus->publish('no_session', $userId, null, $warningStartsAt, [
            'type' => 'work',
            'reason' => WorkSessionClosureReason::Inactivity->value,
        ]);

        return new InactivityDecision(true, $warningStartsAt, $warningEndsAt);
    }

    public function closeAfterBrowserHeartbeatLoss(
        int $userId,
        DateTimeImmutable $lastHeartbeatAt,
        DateTimeImmutable $detectedAt,
        InactivityPolicy $policy = new InactivityPolicy,
    ): InactivityDecision {
        return $this->evaluate($userId, $lastHeartbeatAt, $detectedAt, $policy);
    }
}
