<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Contracts;

use App\Modules\Core\Users\Application\Commands\CreateUserAccountCommand;
use App\Modules\Core\Users\Application\DTOs\CreatedUserAccount;

interface UserAccountRepository
{
    public function existsByEmail(string $email): bool;

    public function createAwaitingFirstPassword(CreateUserAccountCommand $command, string $internalPassword): CreatedUserAccount;
}
