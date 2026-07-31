<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application;

use App\Modules\Core\Users\Application\Commands\CreateUserAccountCommand;
use App\Modules\Core\Users\Application\DTOs\CreatedUserAccount;
use App\Modules\Core\Users\Application\Public\Commands\CreateUserAccountRequest;
use App\Modules\Core\Users\Application\Public\Contracts\UserAccountCreator;

final readonly class PublicUserAccountCreator implements UserAccountCreator
{
    public function __construct(
        private CreateUserAccount $users,
    ) {}

    public function create(CreateUserAccountRequest $request): CreatedUserAccount
    {
        return $this->users->handle(new CreateUserAccountCommand(
            name: $request->name,
            email: $request->email,
            accountSensitivity: $request->accountSensitivity,
        ));
    }
}
