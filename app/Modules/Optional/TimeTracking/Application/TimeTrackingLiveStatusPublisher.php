<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimePublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\PublishRealtimeEvent;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\TableNames\TimeTrackingDatabaseTable;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class TimeTrackingLiveStatusPublisher
{
    public function __construct(
        private RealtimePublisher $realtime,
        private ConnectionInterface $database,
        private UserLookup $users,
        private TeamLookup $teams,
    ) {}

    /**
     * @param  array<string, scalar|null>  $payload
     */
    public function publish(
        string $status,
        int $userId,
        ?int $teamId,
        DateTimeImmutable $occurredAt,
        array $payload = [],
    ): void {
        $userPublicId = $this->userPublicId($userId);
        $teamId ??= $this->latestTeamIdForUser($userId);
        $teamPublicId = $teamId === null ? null : $this->teamPublicId($teamId);

        if ($userPublicId === null || $teamPublicId === null) {
            return;
        }

        $this->realtime->publishRealtime(new PublishRealtimeEvent(
            topic: 'time-tracking',
            eventType: 'time_tracking.status.changed',
            teamPublicId: $teamPublicId,
            payload: [
                'user_public_id' => $userPublicId,
                'status' => $status,
                'occurred_at' => $occurredAt->format(DateTimeImmutable::ATOM),
                ...$payload,
            ],
        ));
    }

    private function userPublicId(int $userId): ?string
    {
        return $this->users->publicIdForInternalId($userId);
    }

    private function teamPublicId(int $teamId): ?string
    {
        return $this->teams->publicIdForInternalId($teamId);
    }

    private function latestTeamIdForUser(int $userId): ?int
    {
        $teamId = $this->database->table(TimeTrackingDatabaseTable::WORK_SESSIONS)
            ->where('user_id', $userId)
            ->orderByRaw('ended_at is null desc')
            ->orderByDesc('started_at')
            ->value('team_id');

        return is_numeric($teamId) ? (int) $teamId : null;
    }
}
