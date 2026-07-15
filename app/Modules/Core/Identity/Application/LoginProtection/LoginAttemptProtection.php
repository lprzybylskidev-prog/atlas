<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\LoginProtection;

use App\Modules\Core\Identity\Application\Contracts\SuspiciousLoginNotifier;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;

final class LoginAttemptProtection
{
    public function __construct(
        private readonly SuspiciousLoginNotifier $notifier,
    ) {}

    public function canAttempt(User $user): bool
    {
        $lockedUntil = $user->loginLockedUntil();

        return $lockedUntil === null || $lockedUntil->isPast();
    }

    public function recordFailedAttempt(User $user): LoginAttemptResult
    {
        $maxFailedAttempts = $this->maxFailedAttempts();
        $failedAttempts = $user->failed_login_attempts + 1;
        $lockCount = $user->login_lock_count;
        $lockedUntil = null;

        if ($failedAttempts >= $maxFailedAttempts) {
            $lockCount++;
            $failedAttempts = 0;
            $lockedUntil = now()->addSeconds($this->lockDurationSeconds($lockCount));
        }

        $user->forceFill([
            'failed_login_attempts' => $failedAttempts,
            'login_lock_count' => $lockCount,
            'login_locked_until' => $lockedUntil,
        ])->save();

        if ($lockedUntil !== null) {
            $this->notifier->accountLocked($user, $lockedUntil);
        }

        return new LoginAttemptResult(
            failedAttempts: $failedAttempts,
            locked: $lockedUntil !== null,
            lockedUntil: $lockedUntil,
        );
    }

    public function recordSuccessfulAttempt(User $user): void
    {
        $user->forceFill([
            'failed_login_attempts' => 0,
            'login_locked_until' => null,
        ])->save();
    }

    public function unlock(User $user): void
    {
        $user->forceFill([
            'failed_login_attempts' => 0,
            'login_locked_until' => null,
        ])->save();
    }

    private function maxFailedAttempts(): int
    {
        $configured = config('atlas.security.login_lock.max_failed_attempts');

        return is_int($configured) && $configured > 0 ? $configured : 10;
    }

    private function lockDurationSeconds(int $lockCount): int
    {
        $durations = config('atlas.security.login_lock.durations_seconds');

        if (! is_array($durations) || $durations === []) {
            return 900;
        }

        $selected = $durations[min($lockCount - 1, count($durations) - 1)] ?? 900;

        return is_int($selected) && $selected > 0 ? $selected : 900;
    }
}
