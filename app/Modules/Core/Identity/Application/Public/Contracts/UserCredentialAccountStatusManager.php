<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use App\Modules\Core\Identity\Application\Public\DTOs\UserCredentialAccountStatus;

interface UserCredentialAccountStatusManager
{
    public function activate(string $publicId): ?UserCredentialAccountStatus;

    public function deactivate(string $publicId): ?UserCredentialAccountStatus;

    public function unlockLogin(string $publicId): ?UserCredentialAccountStatus;

    public function resetMfa(string $publicId): ?UserCredentialAccountStatus;
}
