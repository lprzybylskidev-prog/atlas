<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Presentation\Providers;

use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\Exports\AdminImportRowErrorsDataTableExportProvider;
use App\Modules\Optional\ManagedProcesses\Application\Exports\AdminManagedProcessDefinitionsDataTableExportProvider;
use App\Modules\Optional\ManagedProcesses\Application\Exports\AdminManagedProcessRunsDataTableExportProvider;
use App\Modules\Optional\ManagedProcesses\Application\Exports\AdminManagedProcessSchedulesDataTableExportProvider;
use App\Modules\Optional\ManagedProcesses\Application\Lifecycle\ManagedProcessDataLifecycleParticipant;
use App\Modules\Optional\ManagedProcesses\Application\ManagedProcessesDeactivationGuard;
use App\Modules\Optional\ManagedProcesses\Application\Permissions\ManagedProcessesPermissionCatalog;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Diagnostics\ManagedProcessModuleOperationalDiagnostics;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Fixtures\DatabaseManagedProcessFixtureBuilder;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime\ConfiguredProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime\DatabaseManagedProcessRunInspector;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime\ManagedProcessManager;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime\ManagedProcessRunnerReporter;
use App\Modules\Optional\ManagedProcesses\Presentation\Inertia\ManagedProcessesRouteAvailability;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessFixtureBuilder;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessReporter;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunInspector;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunner;
use Illuminate\Support\ServiceProvider;

final class ManagedProcessesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProcessDefinitionRegistry::class, ConfiguredProcessDefinitionRegistry::class);
        $this->app->bind(ManagedProcessRunner::class, ManagedProcessManager::class);
        $this->app->bind(ManagedProcessReporter::class, ManagedProcessRunnerReporter::class);
        $this->app->bind(ManagedProcessRunInspector::class, DatabaseManagedProcessRunInspector::class);
        if ($this->app->environment(['local', 'development', 'testing'])) {
            $this->app->bind(ManagedProcessFixtureBuilder::class, DatabaseManagedProcessFixtureBuilder::class);
        }
        $this->app->tag([ManagedProcessesPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([ManagedProcessesRouteAvailability::class], 'atlas.inertia_route_availability');
        $this->app->tag([ManagedProcessesDeactivationGuard::class], 'atlas.module_deactivation_guards');
        $this->app->tag([ManagedProcessDataLifecycleParticipant::class], 'atlas.data_lifecycle_participants');
        $this->app->tag([ManagedProcessModuleOperationalDiagnostics::class], 'atlas.module_operational_diagnostics');
        $this->app->tag([
            AdminImportRowErrorsDataTableExportProvider::class,
            AdminManagedProcessDefinitionsDataTableExportProvider::class,
            AdminManagedProcessRunsDataTableExportProvider::class,
            AdminManagedProcessSchedulesDataTableExportProvider::class,
        ], 'atlas.admin_data_table_export_providers');
    }
}
