<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment(['local', 'development']);

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function (?Authenticatable $user = null): bool {
            if (! $this->app->environment(['local', 'development']) || $user === null) {
                return false;
            }

            $userPublicId = data_get($user, 'public_id');
            $teamPublicId = request()->hasSession() ? request()->session()->get('active_team_public_id') : null;

            if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
                return false;
            }

            /** @var EffectivePermissionChecker $checker */
            $checker = app(EffectivePermissionChecker::class);

            return $checker->check(new EffectivePermissionRequest(
                userPublicId: $userPublicId,
                permission: CoreAuthorizationPermissionCatalog::ADMIN_TELESCOPE_VIEW,
                teamPublicId: $teamPublicId,
            ))->allowed;
        });
    }
}
