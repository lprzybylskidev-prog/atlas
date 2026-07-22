<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Providers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditActorContextProvider;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Modules\Core\Identity\Application\Contracts\PasswordHistoryRepository;
use App\Modules\Core\Identity\Application\Contracts\SuspiciousLoginNotifier;
use App\Modules\Core\Identity\Application\Exports\AdminRateLimitPoliciesDataTableExportProvider;
use App\Modules\Core\Identity\Application\LoginProtection\LoginAttemptProtection;
use App\Modules\Core\Identity\Application\Public\Contracts\ImpersonationEligibilityChecker;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountStatusManager;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountStore;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitKeyBuilder;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicyCatalog;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicyRegistrar;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitRejectionRecorder;
use App\Modules\Core\Identity\Application\Sessions\SingleSessionLoginGuard;
use App\Modules\Core\Identity\Application\WebAuthn\Contracts\WebAuthnCredentialRepository;
use App\Modules\Core\Identity\Infrastructure\Notifications\UserSuspiciousLoginNotifier;
use App\Modules\Core\Identity\Infrastructure\Persistence\DatabasePasswordHistoryRepository;
use App\Modules\Core\Identity\Infrastructure\Persistence\DatabaseWebAuthnCredentialRepository;
use App\Modules\Core\Identity\Infrastructure\Persistence\EloquentUserCredentialAccountDirectory;
use App\Modules\Core\Identity\Infrastructure\Persistence\EloquentUserCredentialAccountStatusManager;
use App\Modules\Core\Identity\Infrastructure\Persistence\EloquentUserCredentialAccountStore;
use App\Modules\Core\Identity\Infrastructure\Persistence\RedisUserSessionRegistry;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Identity\Infrastructure\Runtime\SessionAuditActorContextProvider;
use App\Modules\Core\Identity\Presentation\Fortify\Actions\CreateNewUser;
use App\Modules\Core\Identity\Presentation\Fortify\Actions\ResetUserPassword;
use App\Modules\Core\Identity\Presentation\Fortify\Actions\UpdateUserPassword;
use App\Modules\Core\Identity\Presentation\Fortify\Actions\UpdateUserProfileInformation;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PasswordHistoryRepository::class, DatabasePasswordHistoryRepository::class);
        $this->app->bind(SuspiciousLoginNotifier::class, UserSuspiciousLoginNotifier::class);
        $this->app->bind(WebAuthnCredentialRepository::class, DatabaseWebAuthnCredentialRepository::class);
        $this->app->bind(UserCredentialAccountDirectory::class, EloquentUserCredentialAccountDirectory::class);
        $this->app->bind(UserCredentialAccountStore::class, EloquentUserCredentialAccountStore::class);
        $this->app->bind(UserCredentialAccountStatusManager::class, EloquentUserCredentialAccountStatusManager::class);
        $this->app->bind(UserSessionRegistry::class, RedisUserSessionRegistry::class);
        $this->app->bind(ImpersonationEligibilityChecker::class, ImpersonationManager::class);
        $this->app->bind(AuditActorContextProvider::class, SessionAuditActorContextProvider::class);
        $this->app->tag([AdminRateLimitPoliciesDataTableExportProvider::class], 'atlas.admin_data_table_export_providers');
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
        $audit = $this->app->make(SecurityAuditRecorder::class);
        $singleSessionLoginGuard = $this->app->make(SingleSessionLoginGuard::class);

        Fortify::authenticateUsing(function (Request $request) use ($loginAttempts, $audit, $singleSessionLoginGuard): ?User {
            $user = User::query()
                ->where('email', $request->string(Fortify::username())->lower()->toString())
                ->first();

            if (! $user instanceof User || ! $user->canAuthenticate()) {
                $audit->record(new SecurityAuditEvent(
                    module: 'identity',
                    action: 'auth.login_failure',
                    result: 'rejected',
                    source: 'ui',
                    actorPublicId: null,
                    targetPublicId: $user instanceof User ? (string) $user->public_id : null,
                    reason: null,
                    category: SecurityAuditCategory::Authentication,
                    metadata: [
                        'reason' => $user instanceof User ? 'not_authenticatable' : 'invalid_credentials',
                    ],
                ));

                return null;
            }

            $password = $request->input('password');

            if (! is_string($password) || ! Hash::check($password, $user->password)) {
                $loginAttempts->recordFailedAttempt($user);

                return null;
            }

            $singleSessionLoginGuard->resolveLoginConflict($user, $request->boolean('terminate_existing_session'));

            $loginAttempts->recordSuccessfulAttempt($user);

            return $user;
        });

        Event::listen(Logout::class, function (Logout $event) use ($audit): void {
            $userPublicId = data_get($event->user, 'public_id');

            if (is_string($userPublicId)) {
                $this->app->make(UserSessionRegistry::class)->invalidateUser($userPublicId);
            }

            $audit->record(new SecurityAuditEvent(
                module: 'identity',
                action: 'auth.logout',
                result: 'succeeded',
                source: 'ui',
                actorPublicId: is_string($userPublicId) ? $userPublicId : null,
                targetPublicId: is_string($userPublicId) ? $userPublicId : null,
                reason: null,
                category: SecurityAuditCategory::Session,
            ));
        });
    }

    private function registerRateLimitPolicies(): void
    {
        new RateLimitPolicyRegistrar(
            RateLimitPolicyCatalog::fromConfiguredValue(config('atlas.security.rate_limits.policies')),
            new RateLimitKeyBuilder,
            $this->app->make(RateLimitRejectionRecorder::class),
        )->register();
    }
}
