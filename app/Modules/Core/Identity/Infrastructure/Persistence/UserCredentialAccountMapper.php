<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\DTOs\CreatedUserCredentialAccount;
use App\Modules\Core\Identity\Application\Public\DTOs\UserCredentialAccountStatus;
use App\Modules\Core\Identity\Domain\ValueObjects\UserPublicId;

final class UserCredentialAccountMapper
{
    public function created(User $user): CreatedUserCredentialAccount
    {
        return new CreatedUserCredentialAccount(
            publicId: $this->publicId($user)->toString(),
            name: $user->name,
            email: $user->email,
        );
    }

    public function status(User $user): UserCredentialAccountStatus
    {
        return new UserCredentialAccountStatus(
            publicId: $this->publicId($user)->toString(),
            email: $user->email,
            isActive: $user->isActive(),
        );
    }

    private function publicId(User $user): UserPublicId
    {
        return UserPublicId::fromString((string) $user->public_id);
    }
}
