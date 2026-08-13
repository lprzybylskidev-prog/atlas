<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Providers;

use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Presentation\Inertia\ChatRouteAvailability;
use Illuminate\Support\ServiceProvider;

final class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatModuleAccess::class);
        $this->app->tag([ChatPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([ChatRouteAvailability::class], 'atlas.inertia_route_availability');
    }
}
