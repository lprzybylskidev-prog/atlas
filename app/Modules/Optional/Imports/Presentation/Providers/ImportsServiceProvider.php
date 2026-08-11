<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Presentation\Providers;

use App\Modules\Optional\Imports\Application\Contracts\ImportAdapterRegistry;
use App\Modules\Optional\Imports\Application\Contracts\ImportFixtureBuilder;
use App\Modules\Optional\Imports\Application\Permissions\ImportsPermissionCatalog;
use App\Modules\Optional\Imports\Infrastructure\Diagnostics\ImportModuleOperationalDiagnostics;
use App\Modules\Optional\Imports\Infrastructure\Fixtures\DatabaseImportFixtureBuilder;
use App\Modules\Optional\Imports\Infrastructure\Persistence\DatabaseImportAdminVisibility;
use App\Modules\Optional\Imports\Infrastructure\Runtime\ConfiguredImportAdapterRegistry;
use App\Shared\Application\Imports\Contracts\ImportAdminVisibility;
use Illuminate\Support\ServiceProvider;

final class ImportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImportAdapterRegistry::class, ConfiguredImportAdapterRegistry::class);
        $this->app->bind(ImportAdminVisibility::class, DatabaseImportAdminVisibility::class);
        if ($this->app->environment(['local', 'development', 'testing'])) {
            $this->app->bind(ImportFixtureBuilder::class, DatabaseImportFixtureBuilder::class);
        }
        $this->app->tag([ImportsPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([ImportModuleOperationalDiagnostics::class], 'atlas.module_operational_diagnostics');
    }
}
