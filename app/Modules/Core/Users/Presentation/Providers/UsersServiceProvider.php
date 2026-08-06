<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Providers;

use App\Modules\Core\Users\Application\Contracts\FirstPasswordLinkIssuer;
use App\Modules\Core\Users\Application\Contracts\UserAccountRepository;
use App\Modules\Core\Users\Application\Exports\AdminUsersDataTableExportProvider;
use App\Modules\Core\Users\Application\Lifecycle\UserAccountDataLifecycleParticipant;
use App\Modules\Core\Users\Application\Permissions\UserPermissionCatalog;
use App\Modules\Core\Users\Application\Public\Contracts\UserAccountCreator;
use App\Modules\Core\Users\Application\PublicUserAccountCreator;
use App\Modules\Core\Users\Infrastructure\Notifications\LaravelFirstPasswordLinkIssuer;
use App\Modules\Core\Users\Infrastructure\Persistence\IdentityUserAccountRepository;
use App\Modules\Core\Users\Presentation\Console\BootstrapFirstAdministrator;
use App\Modules\Core\Users\Presentation\Inertia\UsersRouteAvailability;
use Illuminate\Support\ServiceProvider;

final class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserAccountRepository::class, IdentityUserAccountRepository::class);
        $this->app->bind(FirstPasswordLinkIssuer::class, LaravelFirstPasswordLinkIssuer::class);
        $this->app->bind(UserAccountCreator::class, PublicUserAccountCreator::class);
        $this->app->tag([UserPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([UsersRouteAvailability::class], 'atlas.inertia_route_availability');
        $this->app->tag([UserAccountDataLifecycleParticipant::class], 'atlas.data_lifecycle_participants');
        $this->app->tag([AdminUsersDataTableExportProvider::class], 'atlas.admin_data_table_export_providers');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BootstrapFirstAdministrator::class,
            ]);
        }
    }
}
