<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimePublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\PublishRealtimeEvent;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class TimeTrackingLiveStatusPublisher
{
    public function __construct(
        private RealtimePublisher $realtime,
        private ConnectionInterface $database,
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
        return $this->stringValue($this->database->table(IdentityDatabaseTable::USERS)->where('id', $userId)->value('public_id'));
    }

    private function teamPublicId(int $teamId): ?string
    {
        return $this->stringValue($this->database->table(TeamsDatabaseTable::TEAMS)->where('id', $teamId)->value('public_id'));
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

    private function stringValue(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
