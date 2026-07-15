<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Providers;

use App\Modules\Core\Identity\Application\Contracts\PasswordHistoryRepository;
use App\Modules\Core\Identity\Application\Contracts\SuspiciousLoginNotifier;
use App\Modules\Core\Identity\Application\LoginProtection\LoginAttemptProtection;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountStatusManager;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountStore;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitKeyBuilder;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicyCatalog;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicyRegistrar;
use App\Modules\Core\Identity\Application\WebAuthn\Contracts\WebAuthnCredentialRepository;
use App\Modules\Core\Identity\Infrastructure\Notifications\UserSuspiciousLoginNotifier;
use App\Modules\Core\Identity\Infrastructure\Persistence\DatabasePasswordHistoryRepository;
use App\Modules\Core\Identity\Infrastructure\Persistence\DatabaseSecurityAuditRecorder;
use App\Modules\Core\Identity\Infrastructure\Persistence\DatabaseWebAuthnCredentialRepository;
use App\Modules\Core\Identity\Infrastructure\Persistence\EloquentUserCredentialAccountStatusManager;
use App\Modules\Core\Identity\Infrastructure\Persistence\EloquentUserCredentialAccountStore;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Identity\Presentation\Fortify\Actions\CreateNewUser;
use App\Modules\Core\Identity\Presentation\Fortify\Actions\ResetUserPassword;
use App\Modules\Core\Identity\Presentation\Fortify\Actions\UpdateUserPassword;
use App\Modules\Core\Identity\Presentation\Fortify\Actions\UpdateUserProfileInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PasswordHistoryRepository::class, DatabasePasswordHistoryRepository::class);
        $this->app->bind(SecurityAuditRecorder::class, DatabaseSecurityAuditRecorder::class);
        $this->app->bind(SuspiciousLoginNotifier::class, UserSuspiciousLoginNotifier::class);
        $this->app->bind(WebAuthnCredentialRepository::class, DatabaseWebAuthnCredentialRepository::class);
        $this->app->bind(UserCredentialAccountStore::class, EloquentUserCredentialAccountStore::class);
        $this->app->bind(UserCredentialAccountStatusManager::class, EloquentUserCredentialAccountStatusManager::class);
    }

    public function boot(): void
    {
        $this->registerRateLimitPolicies();

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        $loginAttempts = $this->app->make(LoginAttemptProtection::class);

        Fortify::authenticateUsing(function (Request $request) use ($loginAttempts): ?User {
            $user = User::query()
                ->where('email', $request->string(Fortify::username())->lower()->toString())
                ->first();

            if (! $user instanceof User || ! $user->canAuthenticate()) {
                return null;
            }

            $password = $request->input('password');

            if (! is_string($password) || ! Hash::check($password, $user->password)) {
                $loginAttempts->recordFailedAttempt($user);

                return null;
            }

            $loginAttempts->recordSuccessfulAttempt($user);

            return $user;
        });
    }

    private function registerRateLimitPolicies(): void
    {
        new RateLimitPolicyRegistrar(
            RateLimitPolicyCatalog::fromConfiguredValue(config('atlas.security.rate_limits.policies')),
            new RateLimitKeyBuilder,
        )->register();
    }
}
