<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application;

use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountStatusManager;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use App\Modules\Core\Users\Application\Commands\UnlockUserAccountCommand;
use App\Modules\Core\Users\Application\DTOs\UserAccountStatus;
use App\Modules\Core\Users\Application\Exceptions\InvalidUserAccountUnlock;
use App\Modules\Core\Users\Application\Exceptions\UserAccountNotFound;

final readonly class UnlockUserAccount
{
    public function __construct(
        private UserCredentialAccountStatusManager $accounts,
        private SecurityAuditRecorder $audit,
    ) {}

    public function handle(UnlockUserAccountCommand $command): UserAccountStatus
    {
        $actorPublicId = trim($command->actorPublicId);
        $reason = trim($command->reason);

        if ($actorPublicId === '') {
            throw InvalidUserAccountUnlock::missingActor();
        }

        if ($reason === '') {
            throw InvalidUserAccountUnlock::missingReason();
        }

        $status = $this->accounts->unlockLogin($command->targetPublicId);

        if ($status === null) {
            $this->recordAudit($command, $actorPublicId, $reason, 'rejected');

            throw UserAccountNotFound::forPublicId($command->targetPublicId);
        }

        $this->recordAudit($command, $actorPublicId, $reason, 'succeeded');

        return new UserAccountStatus(
            publicId: $status->publicId,
            email: $status->email,
            isActive: $status->isActive,
        );
    }

    private function recordAudit(UnlockUserAccountCommand $command, string $actorPublicId, string $reason, string $result): void
    {
        $this->audit->record(new SecurityAuditEvent(
            module: 'identity',
            action: 'user.login_unlock',
            result: $result,
            source: $command->source,
            actorPublicId: $actorPublicId,
            targetPublicId: $command->targetPublicId,
            reason: $reason,
            category: SecurityAuditCategory::Identity,
        ));
    }
}
