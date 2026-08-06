<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\Contracts\WorkSessionStore;
use App\Modules\Optional\TimeTracking\Application\Enums\WorkSessionClosureReason;
use DateTimeImmutable;

final readonly class WorkSessionCoordinator
{
    public function __construct(
        private UserTeamTrackingSettings $trackingSettings,
        private WorkSessionStore $sessions,
        private TimeTrackingAudit $audit,
        private TimeTrackingLiveStatusPublisher $liveStatus,
    ) {}

    public function synchronizeActiveTeam(
        int $userId,
        int $teamId,
        string $laravelSessionId,
        DateTimeImmutable $occurredAt,
        string $moduleKey = 'system',
        string $contextKey = 'System',
    ): void {
        if (! $this->trackingSettings->isEnabledForUserTeam($userId, $teamId)) {
            $this->sessions->closeActiveForUser($userId, WorkSessionClosureReason::TeamUntracked, $occurredAt);
            $this->recordClosure($userId, $teamId, WorkSessionClosureReason::TeamUntracked, $occurredAt);

            return;
        }

        $session = $this->sessions->ensureActive($userId, $teamId, $laravelSessionId, $occurredAt);
        $moduleKey = 'system';
        $contextKey = 'System';
        $this->sessions->recordModuleContext($session->id, $moduleKey, $contextKey, $occurredAt);
        $this->liveStatus->publish('working', $userId, $teamId, $occurredAt, [
            'type' => 'work',
            'context' => $contextKey,
            'module_key' => $moduleKey,
        ]);
    }

    public function endForLogout(int $userId, DateTimeImmutable $occurredAt): void
    {
        $this->sessions->closeActiveForUser($userId, WorkSessionClosureReason::Logout, $occurredAt);
        $this->recordClosure($userId, null, WorkSessionClosureReason::Logout, $occurredAt);
    }

    public function endForModuleUnavailable(int $userId, DateTimeImmutable $occurredAt): void
    {
        $this->sessions->closeActiveForUser($userId, WorkSessionClosureReason::ModuleUnavailable, $occurredAt);
        $this->recordClosure($userId, null, WorkSessionClosureReason::ModuleUnavailable, $occurredAt);
    }

    private function recordClosure(int $userId, ?int $teamId, WorkSessionClosureReason $reason, DateTimeImmutable $occurredAt): void
    {
        $this->audit->record(
            action: 'time_tracking.work_session_closed',
            actorUserId: $userId,
            targetUserId: $userId,
            teamId: $teamId,
            reason: $reason->value,
            after: ['ended_at' => $occurredAt->format(DateTimeImmutable::ATOM), 'reason' => $reason->value],
        );
        $this->liveStatus->publish('no_session', $userId, $teamId, $occurredAt, [
            'type' => 'work',
            'reason' => $reason->value,
        ]);
    }
}
