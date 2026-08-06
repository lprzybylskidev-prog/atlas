<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Settings\Application\Public\Contracts\SecuritySessionSettings;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamSessionLimitSettings;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseUserTeamSessionLimitSettings implements UserTeamSessionLimitSettings
{
    public function __construct(
        private ConnectionInterface $database,
        private SecuritySessionSettings $settings,
    ) {}

    public function resolvedForTeam(string $teamPublicId): array
    {
        $defaultInactivity = $this->settings->inactivityTimeoutMinutes();
        $configuredMaximum = config('atlas.security.sessions.max_lifetime_minutes', 720);
        $defaultMaximum = max(1, is_numeric($configuredMaximum) ? (int) $configuredMaximum : 720);
        $team = $this->database->table(TeamsDatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->first(['inactivity_timeout_minutes', 'session_max_lifetime_minutes']);

        if (! is_object($team)) {
            return [
                'inactivityTimeoutMinutes' => min($defaultInactivity, $defaultMaximum),
                'sessionMaxLifetimeMinutes' => $defaultMaximum,
                'source' => 'default',
            ];
        }

        $inactivity = $this->positiveInt($team->inactivity_timeout_minutes ?? null) ?? $defaultInactivity;
        $maximum = $this->positiveInt($team->session_max_lifetime_minutes ?? null) ?? $defaultMaximum;
        $maximum = max(1, $maximum);

        return [
            'inactivityTimeoutMinutes' => min(max(1, $inactivity), $maximum),
            'sessionMaxLifetimeMinutes' => $maximum,
            'source' => $this->positiveInt($team->inactivity_timeout_minutes ?? null) !== null || $this->positiveInt($team->session_max_lifetime_minutes ?? null) !== null ? 'team' : 'default',
        ];
    }

    public function setTeamOverrides(string $teamPublicId, ?int $inactivityTimeoutMinutes, ?int $sessionMaxLifetimeMinutes): void
    {
        $this->database->table(TeamsDatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->update([
                'inactivity_timeout_minutes' => $inactivityTimeoutMinutes,
                'session_max_lifetime_minutes' => $sessionMaxLifetimeMinutes,
                'updated_at' => now(),
            ]);
    }

    public function resolvedForUserTeam(string $userPublicId, string $teamPublicId): array
    {
        $defaultInactivity = $this->settings->inactivityTimeoutMinutes();
        $configuredMaximum = config('atlas.security.sessions.max_lifetime_minutes', 720);
        $defaultMaximum = max(1, is_numeric($configuredMaximum) ? (int) $configuredMaximum : 720);
        $source = 'default';

        $row = $this->database->table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(IdentityDatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(TeamsDatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
            ->where('teams.public_id', $teamPublicId)
            ->whereNull('team_user_assignments.valid_to')
            ->first([
                'teams.inactivity_timeout_minutes as team_inactivity',
                'teams.session_max_lifetime_minutes as team_maximum',
                'team_user_assignments.inactivity_timeout_minutes as user_team_inactivity',
                'team_user_assignments.session_max_lifetime_minutes as user_team_maximum',
            ]);

        $inactivity = $defaultInactivity;
        $maximum = $defaultMaximum;

        if (is_object($row)) {
            $teamInactivity = $this->positiveInt($row->team_inactivity ?? null);
            $teamMaximum = $this->positiveInt($row->team_maximum ?? null);
            $userTeamInactivity = $this->positiveInt($row->user_team_inactivity ?? null);
            $userTeamMaximum = $this->positiveInt($row->user_team_maximum ?? null);

            if ($teamInactivity !== null || $teamMaximum !== null) {
                $source = 'team';
                $inactivity = $teamInactivity ?? $inactivity;
                $maximum = $teamMaximum ?? $maximum;
            }

            if ($userTeamInactivity !== null || $userTeamMaximum !== null) {
                $source = 'user_team';
                $inactivity = $userTeamInactivity ?? $inactivity;
                $maximum = $userTeamMaximum ?? $maximum;
            }
        }

        $maximum = max(1, $maximum);

        return [
            'inactivityTimeoutMinutes' => min(max(1, $inactivity), $maximum),
            'sessionMaxLifetimeMinutes' => $maximum,
            'source' => $source,
        ];
    }

    public function setUserTeamOverrides(
        string $userPublicId,
        string $teamPublicId,
        ?int $inactivityTimeoutMinutes,
        ?int $sessionMaxLifetimeMinutes,
    ): void {
        $assignmentId = $this->assignmentId($userPublicId, $teamPublicId);

        if ($assignmentId < 1) {
            return;
        }

        $this->database->table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('id', $assignmentId)
            ->update([
                'inactivity_timeout_minutes' => $inactivityTimeoutMinutes,
                'session_max_lifetime_minutes' => $sessionMaxLifetimeMinutes,
                'updated_at' => now(),
            ]);
    }

    private function assignmentId(string $userPublicId, string $teamPublicId): int
    {
        $id = $this->database->table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(IdentityDatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(TeamsDatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
            ->where('teams.public_id', $teamPublicId)
            ->whereNull('team_user_assignments.valid_to')
            ->value('team_user_assignments.id');

        return is_numeric($id) ? (int) $id : 0;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }
}
