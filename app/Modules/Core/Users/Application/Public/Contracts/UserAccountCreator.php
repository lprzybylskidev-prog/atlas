<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Public\Contracts;

use App\Modules\Core\Users\Application\DTOs\CreatedUserAccount;
use App\Modules\Core\Users\Application\Public\Commands\CreateUserAccountRequest;

interface UserAccountCreator
{
    public function create(CreateUserAccountRequest $request): CreatedUserAccount;
}
