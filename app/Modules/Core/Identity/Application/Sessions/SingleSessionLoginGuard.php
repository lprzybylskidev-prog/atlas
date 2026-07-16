<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Sessions;

use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Validation\ValidationException;

final class SingleSessionLoginGuard
{
    public function __construct(
        private readonly UserSessionRegistry $sessions,
        private readonly SecurityAuditRecorder $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function resolveLoginConflict(User $user, bool $terminateExistingSession): void
    {
        $activeSessions = $this->sessions->activeForUser((string) $user->public_id);

        if ($activeSessions === []) {
            return;
        }

        if (! $terminateExistingSession) {
            $this->audit->record(new SecurityAuditEvent(
                module: 'identity',
                action: 'auth.session_conflict',
                result: 'rejected',
                source: 'ui',
                actorPublicId: (string) $user->public_id,
                targetPublicId: (string) $user->public_id,
                reason: null,
                metadata: [
                    'active_session_count' => count($activeSessions),
                ],
            ));

            throw ValidationException::withMessages([
                'session_conflict' => [__('auth.session_conflict')],
            ]);
        }

        $this->sessions->invalidateUser((string) $user->public_id);

        $this->audit->record(new SecurityAuditEvent(
            module: 'identity',
            action: 'auth.session_conflict_resolved',
            result: 'succeeded',
            source: 'ui',
            actorPublicId: (string) $user->public_id,
            targetPublicId: (string) $user->public_id,
            reason: null,
            metadata: [
                'terminated_session_count' => count($activeSessions),
            ],
        ));
    }
}
