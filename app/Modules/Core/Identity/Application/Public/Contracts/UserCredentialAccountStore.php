<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use App\Modules\Core\Identity\Application\Public\Commands\CreateUserCredentialAccount;
use App\Modules\Core\Identity\Application\Public\DTOs\CreatedUserCredentialAccount;

interface UserCredentialAccountStore
{
    public function existsByEmail(string $email): bool;

    public function createAwaitingFirstPassword(CreateUserCredentialAccount $command): CreatedUserCredentialAccount;
}
