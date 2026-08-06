<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

interface UserPasswordUpdater
{
    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function updateAuthenticatedUserPassword(Authenticatable $user, array $input): void;
}
