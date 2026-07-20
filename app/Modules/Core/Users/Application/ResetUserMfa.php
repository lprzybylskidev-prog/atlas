<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application;

use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountStatusManager;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use App\Modules\Core\Users\Application\Commands\ResetUserMfaCommand;
use App\Modules\Core\Users\Application\DTOs\UserAccountStatus;
use App\Modules\Core\Users\Application\Exceptions\InvalidUserMfaReset;
use App\Modules\Core\Users\Application\Exceptions\UserAccountNotFound;

final readonly class ResetUserMfa
{
    public function __construct(
        private UserCredentialAccountStatusManager $accounts,
        private SecurityAuditRecorder $audit,
        private UserSessionRegistry $sessions,
    ) {}

    public function handle(ResetUserMfaCommand $command): UserAccountStatus
    {
        $actorPublicId = trim($command->actorPublicId);
        $reason = trim($command->reason);

        if ($actorPublicId === '') {
            throw InvalidUserMfaReset::missingActor();
        }

        if ($reason === '') {
            throw InvalidUserMfaReset::missingReason();
        }

        $status = $this->accounts->resetMfa($command->targetPublicId);

        if ($status === null) {
            $this->recordAudit($command, $actorPublicId, $reason, 'rejected');

            throw UserAccountNotFound::forPublicId($command->targetPublicId);
        }

        $this->recordAudit($command, $actorPublicId, $reason, 'succeeded');
        $this->sessions->invalidateUser($command->targetPublicId);

        return new UserAccountStatus(
            publicId: $status->publicId,
            email: $status->email,
            isActive: $status->isActive,
        );
    }

    private function recordAudit(ResetUserMfaCommand $command, string $actorPublicId, string $reason, string $result): void
    {
        $this->audit->record(new SecurityAuditEvent(
            module: 'identity',
            action: 'user.mfa_reset',
            result: $result,
            source: $command->source,
            actorPublicId: $actorPublicId,
            targetPublicId: $command->targetPublicId,
            reason: $reason,
            category: SecurityAuditCategory::Mfa,
        ));
    }
}
