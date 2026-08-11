<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Sessions;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionLimitResolver;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Shared\Application\Security\Contracts\SecuritySessionSettings;
use App\Shared\Application\Teams\Contracts\UserTeamSessionLimitSettings;

final readonly class SessionLimitResolver implements UserSessionLimitResolver
{
    public function __construct(
        private SecuritySessionSettings $settings,
        private UserLookup $users,
        private UserTeamSessionLimitSettings $teamSessionLimits,
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

        if ($teamPublicId !== null && $teamPublicId !== '') {
            $userPublicId = $this->users->publicIdForInternalId($userId);
            $teamOnlyLimits = $this->teamSessionLimits->resolvedForTeam($teamPublicId);
            $teamLimits = $userPublicId === null
                ? $teamOnlyLimits
                : $this->teamSessionLimits->resolvedForUserTeam($userPublicId, $teamPublicId);

            if ($teamLimits['source'] === 'default' && $teamOnlyLimits['source'] !== 'default') {
                $teamLimits = $teamOnlyLimits;
            }

            $inactivity = $teamLimits['inactivityTimeoutMinutes'];
            $maximum = $teamLimits['sessionMaxLifetimeMinutes'];
        }

        $maximum = max(1, $maximum);

        return [
            'inactivity' => min(max(1, $inactivity), $maximum),
            'maximum' => $maximum,
        ];
    }
}
