<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Admin;

use App\Modules\Core\Identity\Application\Public\Contracts\UserStepUpAuthentication;
use App\Modules\Core\Identity\Application\Public\DTOs\UserStepUpAuthenticationResult;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\ConfirmPassword;

final readonly class CurrentUserStepUpAuthenticator implements UserStepUpAuthentication
{
    public function __construct(
        private AdministrativeSessionManager $administrativeSessions,
        private StatefulGuard $guard,
        private ConfirmPassword $confirmPassword,
    ) {}

    public function currentUserRequiresMfa(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $this->administrativeSessions->requiresMfa($request, $user);
    }

    public function verifyCurrentUser(Request $request, string $password, ?string $mfaCode = null): UserStepUpAuthenticationResult
    {
        $user = $request->user();

        if (! $user instanceof User || ! ($this->confirmPassword)($this->guard, $user, $password)) {
            return UserStepUpAuthenticationResult::passwordRejected();
        }

        if ($this->administrativeSessions->requiresMfa($request, $user) && ! $this->administrativeSessions->validMfa($user, $mfaCode)) {
            return UserStepUpAuthenticationResult::mfaRejected();
        }

        return UserStepUpAuthenticationResult::verified();
    }
}
