<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Providers;

use App\Modules\Optional\TimeTracking\Application\Contracts\ActiveTimeLockStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\BreakPolicyStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\BreakSessionStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\CorrectionRequestStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\MaintenanceWindowStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\OtherWorkCategoryStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\OtherWorkSessionStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\SettlementPeriodStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\TimeTrackingDeactivationReadiness;
use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\Contracts\WorkSessionStore;
use App\Modules\Optional\TimeTracking\Application\Exports\AdminTimeTrackingBreaksDataTableExportProvider;
use App\Modules\Optional\TimeTracking\Application\Exports\AdminTimeTrackingCorrectionsDataTableExportProvider;
use App\Modules\Optional\TimeTracking\Application\Exports\AdminTimeTrackingDailyDataTableExportProvider;
use App\Modules\Optional\TimeTracking\Application\Exports\AdminTimeTrackingOtherWorkDataTableExportProvider;
use App\Modules\Optional\TimeTracking\Application\Exports\AdminTimeTrackingWorkSessionsDataTableExportProvider;
use App\Modules\Optional\TimeTracking\Application\Exports\TimeTrackingManagerReportDataTableExportProvider;
use App\Modules\Optional\TimeTracking\Application\Exports\TimeTrackingUserReportDataTableExportProvider;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Public\Contracts\UserBreakPolicySettings;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingDeactivationGuard;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseActiveTimeLockStore;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseBreakPolicyStore;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseBreakSessionStore;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseCorrectionRequestStore;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseMaintenanceWindowStore;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseOtherWorkCategoryStore;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseOtherWorkSessionStore;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseSettlementPeriodStore;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseUserBreakPolicySettings;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseUserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\DatabaseWorkSessionStore;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\EmptyTimeTrackingDeactivationReadiness;
use App\Modules\Optional\TimeTracking\Presentation\Http\Middleware\SynchronizeWorkSession;
use App\Modules\Optional\TimeTracking\Presentation\Inertia\TimeTrackingInertiaData;
use App\Modules\Optional\TimeTracking\Presentation\Inertia\TimeTrackingRouteAvailability;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TimeTrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActiveTimeLockStore::class, DatabaseActiveTimeLockStore::class);
        $this->app->bind(BreakPolicyStore::class, DatabaseBreakPolicyStore::class);
        $this->app->bind(BreakSessionStore::class, DatabaseBreakSessionStore::class);
        $this->app->bind(CorrectionRequestStore::class, DatabaseCorrectionRequestStore::class);
        $this->app->bind(MaintenanceWindowStore::class, DatabaseMaintenanceWindowStore::class);
        $this->app->bind(OtherWorkCategoryStore::class, DatabaseOtherWorkCategoryStore::class);
        $this->app->bind(OtherWorkSessionStore::class, DatabaseOtherWorkSessionStore::class);
        $this->app->bind(SettlementPeriodStore::class, DatabaseSettlementPeriodStore::class);
        $this->app->bind(TimeTrackingDeactivationReadiness::class, EmptyTimeTrackingDeactivationReadiness::class);
        $this->app->bind(UserTeamTrackingSettings::class, DatabaseUserTeamTrackingSettings::class);
        $this->app->bind(UserBreakPolicySettings::class, DatabaseUserBreakPolicySettings::class);
        $this->app->bind(WorkSessionStore::class, DatabaseWorkSessionStore::class);
        $this->app->singleton(TimeTrackingModuleAccess::class);
        $this->app->tag([TimeTrackingPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([TimeTrackingInertiaData::class], 'atlas.inertia_shared_data');
        $this->app->tag([TimeTrackingRouteAvailability::class], 'atlas.inertia_route_availability');
        $this->app->tag([TimeTrackingDeactivationGuard::class], 'atlas.module_deactivation_guards');
        $this->app->tag([
            AdminTimeTrackingBreaksDataTableExportProvider::class,
            AdminTimeTrackingCorrectionsDataTableExportProvider::class,
            AdminTimeTrackingDailyDataTableExportProvider::class,
            AdminTimeTrackingOtherWorkDataTableExportProvider::class,
            AdminTimeTrackingWorkSessionsDataTableExportProvider::class,
            TimeTrackingManagerReportDataTableExportProvider::class,
            TimeTrackingUserReportDataTableExportProvider::class,
        ], 'atlas.admin_data_table_export_providers');
    }

    public function boot(): void
    {
        Route::pushMiddlewareToGroup('web', SynchronizeWorkSession::class);
    }
}
