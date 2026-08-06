<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseUserTeamTrackingSettings implements UserTeamTrackingSettings
{
    public function __construct(private ConnectionInterface $database) {}

    public function isEnabledForUserTeam(int $userId, int $teamId): bool
    {
        $assignment = $this->database->table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereNull('valid_to')
            ->first(['id']);

        if (! is_object($assignment)) {
            return false;
        }

        return $this->database->table(DatabaseTable::TIME_TRACKING_USER_TEAM_SETTINGS)
            ->where('team_user_assignment_id', $this->intValue($assignment->id ?? null))
            ->where('tracking_enabled', true)
            ->exists();
    }

    public function setEnabledForAssignment(int $teamUserAssignmentId, bool $enabled): void
    {
        $now = now();

        $this->database->table(DatabaseTable::TIME_TRACKING_USER_TEAM_SETTINGS)->upsert([
            [
                'public_id' => (string) Str::ulid(),
                'team_user_assignment_id' => $teamUserAssignmentId,
                'tracking_enabled' => $enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['team_user_assignment_id'], ['tracking_enabled', 'updated_at']);
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
