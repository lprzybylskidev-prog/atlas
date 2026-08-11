<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\TableNames\TimeTrackingDatabaseTable;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseUserTeamTrackingSettings implements UserTeamTrackingSettings
{
    public function __construct(
        private ConnectionInterface $database,
        private TeamLookup $teams,
    ) {}

    public function isEnabledForUserTeam(int $userId, int $teamId): bool
    {
        $assignmentId = $this->teams->activeAssignmentInternalIdForUserTeam($userId, $teamId);

        if ($assignmentId === null) {
            return false;
        }

        return $this->database->table(TimeTrackingDatabaseTable::USER_TEAM_SETTINGS)
            ->where('team_user_assignment_id', $assignmentId)
            ->where('tracking_enabled', true)
            ->exists();
    }

    public function setEnabledForAssignment(int $teamUserAssignmentId, bool $enabled): void
    {
        $now = now();

        $this->database->table(TimeTrackingDatabaseTable::USER_TEAM_SETTINGS)->upsert([
            [
                'public_id' => (string) Str::ulid(),
                'team_user_assignment_id' => $teamUserAssignmentId,
                'tracking_enabled' => $enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['team_user_assignment_id'], ['tracking_enabled', 'updated_at']);
    }
}
