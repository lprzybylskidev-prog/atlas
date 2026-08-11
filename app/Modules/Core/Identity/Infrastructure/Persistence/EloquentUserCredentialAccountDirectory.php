<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Application\Public\DTOs\AdminUserCredentialAccount;
use App\Modules\Core\Identity\Application\Public\DTOs\UserCredentialAccountOption;
use App\Modules\Core\Identity\Application\Public\DTOs\UserDisplaySummary;
use App\Modules\Core\Identity\Application\Public\DTOs\UserNotificationContact;
use DateTimeInterface;

final class EloquentUserCredentialAccountDirectory implements UserCredentialAccountDirectory, UserLookup
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

    public function internalIdForPublicId(string $userPublicId): ?int
    {
        $id = User::query()->where('public_id', $userPublicId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function publicIdForInternalId(int $userId): ?string
    {
        $publicId = User::query()->whereKey($userId)->value('public_id');

        return is_string($publicId) && $publicId !== '' ? $publicId : null;
    }

    public function publicIdForEmail(string $email): ?string
    {
        $publicId = User::query()->where('email', $email)->value('public_id');

        return is_string($publicId) && $publicId !== '' ? $publicId : null;
    }

    public function internalIdsForPublicIds(array $userPublicIds): array
    {
        if ($userPublicIds === []) {
            return [];
        }

        $ids = [];

        foreach (User::query()
            ->whereIn('public_id', array_values(array_unique($userPublicIds)))
            ->get(['id', 'public_id'])
            ->all() as $user) {
            $publicId = (string) $user->public_id;
            $id = $user->id;

            if ($publicId !== '' && $id > 0) {
                $ids[$publicId] = $id;
            }
        }

        ksort($ids);

        return $ids;
    }

    public function notificationContactForInternalId(int $userId): ?UserNotificationContact
    {
        $user = User::query()->whereKey($userId)->first(['id', 'public_id', 'email', 'email_verified_at']);

        if (! $user instanceof User) {
            return null;
        }

        $emailVerifiedAt = $user->getAttribute('email_verified_at');

        return new UserNotificationContact(
            internalId: (int) $user->id,
            publicId: (string) $user->public_id,
            email: $user->email,
            emailVerifiedAt: $emailVerifiedAt instanceof DateTimeInterface ? $emailVerifiedAt : null,
        );
    }

    public function displaySummariesForPublicIds(array $userPublicIds): array
    {
        if ($userPublicIds === []) {
            return [];
        }

        $summaries = [];

        foreach (User::query()
            ->whereIn('public_id', array_values(array_unique($userPublicIds)))
            ->get(['public_id', 'name', 'email'])
            ->all() as $user) {
            $publicId = (string) $user->public_id;
            $summaries[$publicId] = new UserDisplaySummary(
                publicId: $publicId,
                name: $user->name,
                email: $user->email,
            );
        }

        ksort($summaries);

        return $summaries;
    }

    public function displaySummariesForInternalIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $summaries = [];

        foreach (User::query()
            ->whereIn('id', array_values(array_unique($userIds)))
            ->get(['id', 'public_id', 'name', 'email'])
            ->all() as $user) {
            $id = $user->id;

            $summaries[$id] = new UserDisplaySummary(
                publicId: (string) $user->public_id,
                name: $user->name,
                email: $user->email,
            );
        }

        ksort($summaries);

        return $summaries;
    }

    public function allDisplaySummaries(): array
    {
        $summaries = [];

        foreach (User::query()
            ->orderBy('name')
            ->orderBy('email')
            ->get(['public_id', 'name', 'email'])
            ->all() as $user) {
            $summaries[] = new UserDisplaySummary(
                publicId: (string) $user->public_id,
                name: $user->name,
                email: $user->email,
            );
        }

        return $summaries;
    }

    public function allActiveDisplaySummaries(): array
    {
        $summaries = [];

        foreach (User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('email')
            ->get(['public_id', 'name', 'email'])
            ->all() as $user) {
            $summaries[] = new UserDisplaySummary(
                publicId: (string) $user->public_id,
                name: $user->name,
                email: $user->email,
            );
        }

        return $summaries;
    }

    public function emailExists(string $email, ?string $exceptPublicId = null): bool
    {
        return User::query()
            ->where('email', $email)
            ->when($exceptPublicId !== null, static fn ($query) => $query->where('public_id', '<>', $exceptPublicId))
            ->exists();
    }

    public function updateIdentity(
        string $publicId,
        string $name,
        string $email,
        string $accountSensitivity,
    ): ?AdminUserCredentialAccount {
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
