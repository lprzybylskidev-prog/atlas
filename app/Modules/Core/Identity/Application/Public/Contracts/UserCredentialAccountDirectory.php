<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use App\Modules\Core\Identity\Application\Public\DTOs\AdminUserCredentialAccount;
use App\Modules\Core\Identity\Application\Public\DTOs\UserCredentialAccountOption;

interface UserCredentialAccountDirectory
{
    /**
     * @return list<UserCredentialAccountOption>
     */
    public function allOptions(): array;

    /**
     * @return list<AdminUserCredentialAccount>
     */
    public function allAdminRows(): array;

    public function findAdminRow(string $publicId): ?AdminUserCredentialAccount;

    public function updateIdentity(string $publicId, string $name, string $email): ?AdminUserCredentialAccount;

    public function verifyEmail(string $publicId): ?AdminUserCredentialAccount;

    public function requireEmailVerification(string $publicId): ?AdminUserCredentialAccount;
}
