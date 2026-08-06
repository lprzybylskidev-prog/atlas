<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Sessions;

use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionLimitResolver;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Settings\Application\Public\Contracts\SecuritySessionSettings;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class SessionLimitResolver implements UserSessionLimitResolver
{
    public function __construct(
        private SecuritySessionSettings $settings,
    ) {}

    /**
     * @return array{inactivity: int, maximum: int}
     */
    public function limitsFor(User $user, ?string $teamPublicId = null): array
    {
        return $this->limitsForUserId((int) $user->id, $teamPublicId);
    }

    /**
     * @return array{inactivity: int, maximum: int}
     */
    public function limitsForUserId(int $userId, ?string $teamPublicId = null): array
    {
        $inactivity = $this->settings->inactivityTimeoutMinutes();
        $configuredMaximum = config('atlas.security.sessions.max_lifetime_minutes', 720);
        $maximum = is_numeric($configuredMaximum) ? (int) $configuredMaximum : 720;

        $teamLimits = $this->teamLimits($teamPublicId);
        $assignmentLimits = $this->assignmentLimits($userId, $teamPublicId);

        $inactivity = $assignmentLimits['inactivity']
            ?? $teamLimits['inactivity']
            ?? $inactivity;
        $maximum = $assignmentLimits['maximum']
            ?? $teamLimits['maximum']
            ?? $maximum;

        $maximum = max(1, $maximum);

        return [
            'inactivity' => min(max(1, $inactivity), $maximum),
            'maximum' => $maximum,
        ];
    }

    /**
     * @return array{inactivity: int|null, maximum: int|null}
     */
    private function teamLimits(?string $teamPublicId): array
    {
        if ($teamPublicId === null || $teamPublicId === '') {
            return ['inactivity' => null, 'maximum' => null];
        }

        $team = DB::table(DatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->first(['inactivity_timeout_minutes', 'session_max_lifetime_minutes']);

        if (! is_object($team)) {
            return ['inactivity' => null, 'maximum' => null];
        }

        return [
            'inactivity' => $this->positiveInt($team->inactivity_timeout_minutes ?? null),
            'maximum' => $this->positiveInt($team->session_max_lifetime_minutes ?? null),
        ];
    }

    /**
     * @return array{inactivity: int|null, maximum: int|null}
     */
    private function assignmentLimits(int $userId, ?string $teamPublicId): array
    {
        if ($userId < 1 || $teamPublicId === null || $teamPublicId === '') {
            return ['inactivity' => null, 'maximum' => null];
        }

        $assignment = DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('team_user_assignments.user_id', $userId)
            ->where('teams.public_id', $teamPublicId)
            ->whereNull('team_user_assignments.valid_to')
            ->first([
                'team_user_assignments.inactivity_timeout_minutes',
                'team_user_assignments.session_max_lifetime_minutes',
            ]);

        if (! is_object($assignment)) {
            return ['inactivity' => null, 'maximum' => null];
        }

        return [
            'inactivity' => $this->positiveInt($assignment->inactivity_timeout_minutes ?? null),
            'maximum' => $this->positiveInt($assignment->session_max_lifetime_minutes ?? null),
        ];
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
