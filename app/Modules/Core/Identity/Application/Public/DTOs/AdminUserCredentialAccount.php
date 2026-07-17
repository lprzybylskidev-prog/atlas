<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\DTOs;

final readonly class AdminUserCredentialAccount
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $name,
        public string $email,
        public bool $isActive,
        public bool $emailVerified,
        public bool $firstPasswordSet,
        public bool $loginLocked,
        public bool $mfaEnabled,
        public bool $online,
        public string $accountSensitivity,
        public ?string $emailVerifiedAt,
        public ?string $twoFactorConfirmedAt,
        public ?string $firstPasswordSetAt,
        public ?string $deactivatedAt,
        public int $failedLoginAttempts,
        public int $loginLockCount,
        public ?string $loginLockedUntil,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
