<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountStatusManager;
use App\Modules\Core\Users\Application\Commands\DeactivateUserAccountCommand;
use App\Modules\Core\Users\Application\DTOs\UserAccountStatus;
use App\Modules\Core\Users\Application\Exceptions\UserAccountNotFound;

final readonly class DeactivateUserAccount
{
    public function __construct(
        private UserCredentialAccountStatusManager $accounts,
    ) {}

    public function handle(DeactivateUserAccountCommand $command): UserAccountStatus
    {
        $status = $this->accounts->deactivate($command->publicId);

        if ($status === null) {
            throw UserAccountNotFound::forPublicId($command->publicId);
        }

        return new UserAccountStatus(
            publicId: $status->publicId,
            email: $status->email,
            isActive: $status->isActive,
        );
    }
}
