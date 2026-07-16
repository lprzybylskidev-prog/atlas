<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Sessions;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Settings\Application\Public\Contracts\SecuritySessionSettings;

final readonly class SessionLimitResolver
{
    public function __construct(
        private SecuritySessionSettings $settings,
    ) {}

    /**
     * @return array{inactivity: int, maximum: int}
     */
    public function limitsFor(User $user): array
    {
        $inactivity = $this->settings->inactivityTimeoutMinutes();
        $configuredMaximum = config('atlas.security.sessions.max_lifetime_minutes', 720);
        $maximum = is_numeric($configuredMaximum) ? (int) $configuredMaximum : 720;

        if (is_int($user->inactivity_timeout_minutes) && $user->inactivity_timeout_minutes > 0) {
            $inactivity = $user->inactivity_timeout_minutes;
        }

        if (is_int($user->session_max_lifetime_minutes) && $user->session_max_lifetime_minutes > 0) {
            $maximum = $user->session_max_lifetime_minutes;
        }

        return [
            'inactivity' => max(1, $inactivity),
            'maximum' => max(1, $maximum),
        ];
    }
}
