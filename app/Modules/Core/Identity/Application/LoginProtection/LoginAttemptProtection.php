<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\LoginProtection;

use App\Modules\Core\Identity\Application\Contracts\SuspiciousLoginNotifier;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;

final class LoginAttemptProtection
{
    public function __construct(
        private readonly SuspiciousLoginNotifier $notifier,
        private readonly SecurityAuditRecorder $audit,
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
            $this->audit->record(new SecurityAuditEvent(
                module: 'identity',
                action: 'auth.login_lock',
                result: 'succeeded',
                source: 'ui',
                actorPublicId: null,
                targetPublicId: (string) $user->public_id,
                reason: null,
                metadata: [
                    'locked_until' => $lockedUntil->toISOString(),
                    'lock_count' => $lockCount,
                ],
            ));
        }

        $this->audit->record(new SecurityAuditEvent(
            module: 'identity',
            action: 'auth.login_failure',
            result: 'rejected',
            source: 'ui',
            actorPublicId: null,
            targetPublicId: (string) $user->public_id,
            reason: null,
            metadata: [
                'failed_attempts' => $failedAttempts,
                'locked' => $lockedUntil !== null,
            ],
        ));

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

        $this->audit->record(new SecurityAuditEvent(
            module: 'identity',
            action: 'auth.login_success',
            result: 'succeeded',
            source: 'ui',
            actorPublicId: (string) $user->public_id,
            targetPublicId: (string) $user->public_id,
            reason: null,
        ));
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
