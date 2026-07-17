<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Application\Public\DTOs\AdminUserCredentialAccount;
use App\Modules\Core\Identity\Application\Public\DTOs\UserCredentialAccountOption;
use DateTimeInterface;

final class EloquentUserCredentialAccountDirectory implements UserCredentialAccountDirectory
{
    public function __construct(
        private readonly UserSessionRegistry $sessions,
    ) {}

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
        $online = array_flip($this->sessions->onlineUserPublicIds());

        foreach (User::query()->orderBy('name')->get() as $user) {
            $rows[] = $this->adminRow($user, isset($online[(string) $user->public_id]));
        }

        return $rows;
    }

    public function findAdminRow(string $publicId): ?AdminUserCredentialAccount
    {
        $user = User::query()->where('public_id', $publicId)->first();

        if (! $user instanceof User) {
            return null;
        }

        return $this->adminRow($user, in_array((string) $user->public_id, $this->sessions->onlineUserPublicIds(), true));
    }

    public function publicIdExists(string $publicId): bool
    {
        return User::query()->where('public_id', $publicId)->exists();
    }

    public function emailExists(string $email, ?string $exceptPublicId = null): bool
    {
        return User::query()
            ->where('email', $email)
            ->when($exceptPublicId !== null, static fn ($query) => $query->where('public_id', '<>', $exceptPublicId))
            ->exists();
    }

    public function updateIdentity(string $publicId, string $name, string $email, string $accountSensitivity): ?AdminUserCredentialAccount
    {
        $user = User::query()->where('public_id', $publicId)->first();

        if (! $user instanceof User) {
            return null;
        }

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'account_sensitivity' => $accountSensitivity,
        ])->save();

        return $this->adminRow($user, in_array((string) $user->public_id, $this->sessions->onlineUserPublicIds(), true));
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

        return $this->adminRow($user, in_array((string) $user->public_id, $this->sessions->onlineUserPublicIds(), true));
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
        $this->sessions->invalidateUser((string) $user->public_id);

        return $this->adminRow($user, false);
    }

    private function adminRow(User $user, bool $online = false): AdminUserCredentialAccount
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
            online: $online,
            accountSensitivity: $user->account_sensitivity,
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
