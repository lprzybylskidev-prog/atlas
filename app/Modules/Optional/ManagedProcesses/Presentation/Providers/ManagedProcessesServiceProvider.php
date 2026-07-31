<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Presentation\Providers;

use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\Exports\AdminImportRowErrorsDataTableExportProvider;
use App\Modules\Optional\ManagedProcesses\Application\Exports\AdminManagedProcessDefinitionsDataTableExportProvider;
use App\Modules\Optional\ManagedProcesses\Application\Exports\AdminManagedProcessRunsDataTableExportProvider;
use App\Modules\Optional\ManagedProcesses\Application\Exports\AdminManagedProcessSchedulesDataTableExportProvider;
use App\Modules\Optional\ManagedProcesses\Application\ManagedProcessesDeactivationGuard;
use App\Modules\Optional\ManagedProcesses\Application\Permissions\ManagedProcessesPermissionCatalog;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessReporter;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunInspector;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime\ConfiguredProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime\DatabaseManagedProcessRunInspector;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime\ManagedProcessManager;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime\ManagedProcessRunnerReporter;
use Illuminate\Support\ServiceProvider;

final class ManagedProcessesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProcessDefinitionRegistry::class, ConfiguredProcessDefinitionRegistry::class);
        $this->app->bind(ManagedProcessRunner::class, ManagedProcessManager::class);
        $this->app->bind(ManagedProcessReporter::class, ManagedProcessRunnerReporter::class);
        $this->app->bind(ManagedProcessRunInspector::class, DatabaseManagedProcessRunInspector::class);
        $this->app->tag([ManagedProcessesPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([ManagedProcessesDeactivationGuard::class], 'atlas.module_deactivation_guards');
        $this->app->tag([
            AdminImportRowErrorsDataTableExportProvider::class,
            AdminManagedProcessDefinitionsDataTableExportProvider::class,
            AdminManagedProcessRunsDataTableExportProvider::class,
            AdminManagedProcessSchedulesDataTableExportProvider::class,
        ], 'atlas.admin_data_table_export_providers');
    }
}
