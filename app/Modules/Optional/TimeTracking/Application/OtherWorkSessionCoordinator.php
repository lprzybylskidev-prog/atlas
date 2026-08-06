<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Optional\TimeTracking\Application\Contracts\OtherWorkSessionStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\ActiveOtherWorkSession;
use App\Modules\Optional\TimeTracking\Application\Enums\OtherWorkApprovalStatus;
use App\Modules\Optional\TimeTracking\Application\Enums\OtherWorkClosureReason;
use DateTimeImmutable;

final readonly class OtherWorkSessionCoordinator
{
    public function __construct(
        private OtherWorkSessionStore $otherWork,
        private TimeTrackingAudit $audit,
        private TimeTrackingLiveStatusPublisher $liveStatus,
    ) {}

    public function start(int $userId, ?string $categoryKey, string $description, DateTimeImmutable $startedAt): ActiveOtherWorkSession
    {
        $session = $this->otherWork->startForActiveWorkSession($userId, $categoryKey, $description, $startedAt);

        $this->audit->record(
            action: 'time_tracking.other_work_started',
            actorUserId: $userId,
            targetUserId: $userId,
            teamId: $session->teamId,
            aggregateType: 'time_tracking_other_work',
            aggregatePublicId: $session->publicId,
            after: [
                'started_at' => $startedAt->format(DateTimeImmutable::ATOM),
                'category_key' => $categoryKey,
                'description_present' => trim($description) !== '',
            ],
        );
        $this->liveStatus->publish('other_work', $userId, $session->teamId, $startedAt, [
            'type' => 'other_work',
            'context' => $categoryKey,
        ]);

        return $session;
    }

    public function end(int $userId, DateTimeImmutable $endedAt, ?string $endNote = null): void
    {
        $this->otherWork->closeActiveForUser($userId, OtherWorkClosureReason::Normal, $endedAt, $endNote);
        $this->audit->record(
            action: 'time_tracking.other_work_ended',
            actorUserId: $userId,
            targetUserId: $userId,
            after: [
                'ended_at' => $endedAt->format(DateTimeImmutable::ATOM),
                'reason' => OtherWorkClosureReason::Normal->value,
                'end_note_present' => is_string($endNote) && trim($endNote) !== '',
            ],
        );
        $this->liveStatus->publish('working', $userId, null, $endedAt, [
            'type' => 'other_work',
        ]);
    }

    public function forceClose(int $userId, DateTimeImmutable $endedAt, ?string $endNote = null): void
    {
        $this->otherWork->closeActiveForUser($userId, OtherWorkClosureReason::Forced, $endedAt, $endNote);
        $this->audit->record(
            action: 'time_tracking.other_work_force_closed',
            targetUserId: $userId,
            reason: OtherWorkClosureReason::Forced->value,
            after: [
                'ended_at' => $endedAt->format(DateTimeImmutable::ATOM),
                'end_note_present' => is_string($endNote) && trim($endNote) !== '',
            ],
        );
        $this->liveStatus->publish('under_review', $userId, null, $endedAt, [
            'type' => 'other_work',
        ]);
    }

    public function moveActiveToUnderReview(int $userId, string $reason): void
    {
        $this->otherWork->moveActiveToUnderReview($userId, $reason);
        $this->audit->record(
            action: 'time_tracking.other_work_moved_under_review',
            targetUserId: $userId,
            reason: $reason,
        );
        $this->liveStatus->publish('under_review', $userId, null, new DateTimeImmutable, [
            'type' => 'other_work',
        ]);
    }

    public function decidePending(
        int $otherWorkId,
        int $actorUserId,
        int $targetUserId,
        int $teamId,
        string $publicId,
        OtherWorkApprovalStatus $status,
        string $reason,
        DateTimeImmutable $decidedAt,
    ): bool {
        $decided = $this->otherWork->decidePending($otherWorkId, $status);

        if ($decided) {
            $this->audit->record(
                action: 'time_tracking.other_work_'.$status->value,
                actorUserId: $actorUserId,
                targetUserId: $targetUserId,
                teamId: $teamId,
                aggregateType: 'time_tracking_other_work',
                aggregatePublicId: $publicId,
                reason: $reason,
                after: [
                    'approval_status' => $status->value,
                    'requires_manager_review' => false,
                    'decided_at' => $decidedAt->format(DateTimeImmutable::ATOM),
                ],
            );
        }

        return $decided;
    }
}
