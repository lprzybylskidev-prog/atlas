<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Providers;

use App\Modules\Core\Teams\Application\Permissions\TeamPermissionCatalog;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use App\Modules\Core\Teams\Infrastructure\Persistence\DatabaseManagerHierarchy;
use App\Modules\Core\Teams\Infrastructure\Persistence\DatabaseUserTeamMembershipManager;
use App\Modules\Core\Teams\Infrastructure\Persistence\EloquentBootstrapTeamProvider;
use Illuminate\Support\ServiceProvider;

final class TeamsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BootstrapTeamProvider::class, EloquentBootstrapTeamProvider::class);
        $this->app->bind(UserTeamMembershipManager::class, DatabaseUserTeamMembershipManager::class);
        $this->app->bind(ManagerHierarchy::class, DatabaseManagerHierarchy::class);
        $this->app->tag([TeamPermissionCatalog::class], 'atlas.permission_catalogs');
    }
}
