<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\DTOs\AdminUserCredentialAccount;
use App\Modules\Core\Identity\Application\Public\DTOs\UserCredentialAccountOption;
use DateTimeInterface;

final class EloquentUserCredentialAccountDirectory implements UserCredentialAccountDirectory
{
    public function allOptions(): array
    {
        $options = [];

        foreach (User::query()
            ->orderBy('name')
            ->get(['public_id', 'name', 'email'])
            ->all() as $user) {
            $options[] = new UserCredentialAccountOption(
                publicId: (string) $user->public_id,
                name: $user->name,
                email: $user->email,
            );
        }

        return $options;
    }

    public function allAdminRows(): array
    {
        $rows = [];

        foreach (User::query()->orderBy('name')->get() as $user) {
            $rows[] = $this->adminRow($user);
        }

        return $rows;
    }

    public function findAdminRow(string $publicId): ?AdminUserCredentialAccount
    {
        $user = User::query()->where('public_id', $publicId)->first();

        if (! $user instanceof User) {
            return null;
        }

        return $this->adminRow($user);
    }

    public function updateIdentity(string $publicId, string $name, string $email): ?AdminUserCredentialAccount
    {
        $user = User::query()->where('public_id', $publicId)->first();

        if (! $user instanceof User) {
            return null;
        }

        $user->forceFill([
            'name' => $name,
            'email' => $email,
        ])->save();

        return $this->adminRow($user);
    }

    public function verifyEmail(string $publicId): ?AdminUserCredentialAccount
    {
        $user = User::query()->where('public_id', $publicId)->first();

        if (! $user instanceof User) {
            return null;
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $this->adminRow($user);
    }

    public function requireEmailVerification(string $publicId): ?AdminUserCredentialAccount
    {
        $user = User::query()->where('public_id', $publicId)->first();

        if (! $user instanceof User) {
            return null;
        }

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();

        return $this->adminRow($user);
    }

    private function adminRow(User $user): AdminUserCredentialAccount
    {
        return new AdminUserCredentialAccount(
            id: $user->internalId(),
            publicId: (string) $user->public_id,
            name: $user->name,
            email: $user->email,
            isActive: (bool) $user->is_active,
            emailVerified: $user->email_verified_at !== null,
            firstPasswordSet: $user->first_password_set_at !== null,
            loginLocked: $user->isLoginLocked(),
            mfaEnabled: $user->two_factor_confirmed_at !== null,
            emailVerifiedAt: $this->optionalDateTimeString($user->email_verified_at),
            twoFactorConfirmedAt: $this->optionalDateTimeString($user->two_factor_confirmed_at),
            firstPasswordSetAt: $this->optionalDateTimeString($user->first_password_set_at),
            deactivatedAt: $this->optionalDateTimeString($user->deactivated_at),
            failedLoginAttempts: (int) $user->failed_login_attempts,
            loginLockCount: (int) $user->login_lock_count,
            loginLockedUntil: $this->optionalDateTimeString($user->loginLockedUntil()),
            createdAt: $this->dateTimeString($user->created_at),
            updatedAt: $this->dateTimeString($user->updated_at),
        );
    }

    private function optionalDateTimeString(mixed $value): ?string
    {
        if (! $value instanceof DateTimeInterface) {
            return null;
        }

        return $value->format(DATE_ATOM);
    }

    private function dateTimeString(mixed $value): string
    {
        return $this->optionalDateTimeString($value) ?? '';
    }
}
