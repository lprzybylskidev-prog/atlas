<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Presentation\Providers;

use App\Modules\Optional\Integrations\Application\Contracts\IntegrationRegistry;
use App\Modules\Optional\Integrations\Application\Exports\AdminIntegrationAdaptersDataTableExportProvider;
use App\Modules\Optional\Integrations\Application\Exports\AdminIntegrationRunsDataTableExportProvider;
use App\Modules\Optional\Integrations\Application\IntegrationsDeactivationGuard;
use App\Modules\Optional\Integrations\Application\Permissions\IntegrationsPermissionCatalog;
use App\Modules\Optional\Integrations\Application\Public\Contracts\ExternalApiAccessPolicy;
use App\Modules\Optional\Integrations\Application\Public\Contracts\ExternalIdMappingStore;
use App\Modules\Optional\Integrations\Application\Public\Contracts\IntegrationIdempotencyStore;
use App\Modules\Optional\Integrations\Application\Public\Contracts\SynchronizationHistory;
use App\Modules\Optional\Integrations\Infrastructure\Persistence\DatabaseIntegrationStore;
use App\Modules\Optional\Integrations\Infrastructure\Runtime\ConfiguredIntegrationRegistry;
use App\Modules\Optional\Integrations\Presentation\Inertia\IntegrationsRouteAvailability;
use Illuminate\Support\ServiceProvider;

final class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationRegistry::class, ConfiguredIntegrationRegistry::class);
        $this->app->bind(ExternalApiAccessPolicy::class, DatabaseIntegrationStore::class);
        $this->app->bind(ExternalIdMappingStore::class, DatabaseIntegrationStore::class);
        $this->app->bind(IntegrationIdempotencyStore::class, DatabaseIntegrationStore::class);
        $this->app->bind(SynchronizationHistory::class, DatabaseIntegrationStore::class);
        $this->app->tag([IntegrationsPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([IntegrationsRouteAvailability::class], 'atlas.inertia_route_availability');
        $this->app->tag([IntegrationsDeactivationGuard::class], 'atlas.module_deactivation_guards');
        $this->app->tag([
            AdminIntegrationAdaptersDataTableExportProvider::class,
            AdminIntegrationRunsDataTableExportProvider::class,
        ], 'atlas.admin_data_table_export_providers');
    }
}
