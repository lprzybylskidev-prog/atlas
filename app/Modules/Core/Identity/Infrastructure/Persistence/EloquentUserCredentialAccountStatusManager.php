<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountStatusManager;
use App\Modules\Core\Identity\Application\Public\DTOs\UserCredentialAccountStatus;

final class EloquentUserCredentialAccountStatusManager implements UserCredentialAccountStatusManager
{
    public function __construct(
        private readonly UserCredentialAccountMapper $mapper,
    ) {}

    public function activate(string $publicId): ?UserCredentialAccountStatus
    {
        $user = $this->findByPublicId($publicId);

        if (! $user instanceof User) {
            return null;
        }

        $user->forceFill([
            'is_active' => true,
            'deactivated_at' => null,
        ])->save();

        return $this->mapper->status($user);
    }

    public function deactivate(string $publicId): ?UserCredentialAccountStatus
    {
        $user = $this->findByPublicId($publicId);

        if (! $user instanceof User) {
            return null;
        }

        $user->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
        ])->save();

        return $this->mapper->status($user);
    }

    public function unlockLogin(string $publicId): ?UserCredentialAccountStatus
    {
        $user = $this->findByPublicId($publicId);

        if (! $user instanceof User) {
            return null;
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'login_locked_until' => null,
        ])->save();

        return $this->mapper->status($user);
    }

    public function resetMfa(string $publicId): ?UserCredentialAccountStatus
    {
        $user = $this->findByPublicId($publicId);

        if (! $user instanceof User) {
            return null;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $this->mapper->status($user);
    }

    private function findByPublicId(string $publicId): ?User
    {
        return User::query()->where('public_id', $publicId)->first();
    }
}
