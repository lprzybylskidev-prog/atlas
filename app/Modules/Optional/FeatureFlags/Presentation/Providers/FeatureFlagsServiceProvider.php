<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Presentation\Providers;

use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagRegistry;
use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagStore;
use App\Modules\Optional\FeatureFlags\Application\Exports\AdminFeatureFlagHistoryDataTableExportProvider;
use App\Modules\Optional\FeatureFlags\Application\Exports\AdminFeatureFlagsDataTableExportProvider;
use App\Modules\Optional\FeatureFlags\Application\FeatureFlagDefinitions;
use App\Modules\Optional\FeatureFlags\Application\FeatureFlagService;
use App\Modules\Optional\FeatureFlags\Application\Permissions\FeatureFlagsPermissionCatalog;
use App\Modules\Optional\FeatureFlags\Application\Public\Contracts\FeatureFlagEvaluator;
use App\Modules\Optional\FeatureFlags\Infrastructure\Persistence\DatabaseFeatureFlagStore;
use App\Modules\Optional\FeatureFlags\Presentation\Inertia\FeatureFlagsRouteAvailability;
use Illuminate\Support\ServiceProvider;

final class FeatureFlagsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureFlagRegistry::class, FeatureFlagDefinitions::class);
        $this->app->bind(FeatureFlagStore::class, DatabaseFeatureFlagStore::class);
        $this->app->bind(FeatureFlagEvaluator::class, FeatureFlagService::class);

        $this->app->tag([FeatureFlagsPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([FeatureFlagsRouteAvailability::class], 'atlas.inertia_route_availability');
        $this->app->tag([
            AdminFeatureFlagsDataTableExportProvider::class,
            AdminFeatureFlagHistoryDataTableExportProvider::class,
        ], 'atlas.admin_data_table_export_providers');
    }
}
