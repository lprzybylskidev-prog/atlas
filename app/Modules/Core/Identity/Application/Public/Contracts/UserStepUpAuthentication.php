<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use App\Modules\Core\Identity\Application\Public\DTOs\UserStepUpAuthenticationResult;
use Illuminate\Http\Request;

interface UserStepUpAuthentication
{
    public function currentUserRequiresMfa(Request $request): bool;

    public function verifyCurrentUser(Request $request, string $password, ?string $mfaCode = null): UserStepUpAuthenticationResult;
}
