<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Optional\TimeTracking\Application\Contracts\BreakSessionStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\ActiveBreakSession;
use App\Modules\Optional\TimeTracking\Application\Enums\BreakClosureReason;
use DateTimeImmutable;

final readonly class BreakSessionCoordinator
{
    public function __construct(
        private BreakSessionStore $breaks,
        private TimeTrackingAudit $audit,
        private TimeTrackingLiveStatusPublisher $liveStatus,
    ) {}

    public function start(int $userId, DateTimeImmutable $startedAt): ActiveBreakSession
    {
        $break = $this->breaks->startForActiveWorkSession($userId, $startedAt);

        $this->audit->record(
            action: 'time_tracking.break_started',
            actorUserId: $userId,
            targetUserId: $userId,
            teamId: $break->teamId,
            aggregateType: 'time_tracking_break',
            aggregatePublicId: $break->publicId,
            after: ['started_at' => $startedAt->format(DateTimeImmutable::ATOM)],
        );
        $this->liveStatus->publish('break', $userId, $break->teamId, $startedAt, [
            'type' => 'break',
            'context' => 'Break',
        ]);

        return $break;
    }

    public function end(int $userId, DateTimeImmutable $endedAt): void
    {
        $this->breaks->closeActiveForUser($userId, BreakClosureReason::Normal, $endedAt, false);
        $this->audit->record(
            action: 'time_tracking.break_ended',
            actorUserId: $userId,
            targetUserId: $userId,
            after: ['ended_at' => $endedAt->format(DateTimeImmutable::ATOM), 'reason' => BreakClosureReason::Normal->value],
        );
        $this->liveStatus->publish('working', $userId, null, $endedAt, [
            'type' => 'break',
            'context' => 'Break',
        ]);
    }

    public function forceClose(int $userId, DateTimeImmutable $endedAt): void
    {
        $this->breaks->closeActiveForUser($userId, BreakClosureReason::Forced, $endedAt, true);
        $this->audit->record(
            action: 'time_tracking.break_force_closed',
            targetUserId: $userId,
            reason: BreakClosureReason::Forced->value,
            after: ['ended_at' => $endedAt->format(DateTimeImmutable::ATOM), 'requires_manager_review' => true],
        );
        $this->liveStatus->publish('under_review', $userId, null, $endedAt, [
            'type' => 'break',
            'context' => 'Break',
        ]);
    }

    public function closeExpired(DateTimeImmutable $now): int
    {
        $closed = $this->breaks->closeExpired($now);

        if ($closed > 0) {
            $this->audit->record(
                action: 'time_tracking.breaks_expired_closed',
                reason: BreakClosureReason::MaximumDuration->value,
                after: ['closed_count' => $closed, 'closed_at' => $now->format(DateTimeImmutable::ATOM)],
                source: 'scheduler',
            );
        }

        return $closed;
    }

    public function recordDueReminders(DateTimeImmutable $now): int
    {
        $recorded = $this->breaks->recordDueReminders($now);

        if ($recorded > 0) {
            $this->audit->record(
                action: 'time_tracking.break_reminders_recorded',
                after: ['recorded_count' => $recorded, 'recorded_at' => $now->format(DateTimeImmutable::ATOM)],
                source: 'scheduler',
            );
        }

        return $recorded;
    }
}
