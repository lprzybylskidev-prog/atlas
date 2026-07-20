<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Presentation\Providers;

use App\Modules\Optional\Imports\Application\Contracts\ImportAdapterRegistry;
use App\Modules\Optional\Imports\Application\Permissions\ImportsPermissionCatalog;
use App\Modules\Optional\Imports\Infrastructure\Runtime\ConfiguredImportAdapterRegistry;
use Illuminate\Support\ServiceProvider;

final class ImportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImportAdapterRegistry::class, ConfiguredImportAdapterRegistry::class);
        $this->app->tag([ImportsPermissionCatalog::class], 'atlas.permission_catalogs');
    }
}
