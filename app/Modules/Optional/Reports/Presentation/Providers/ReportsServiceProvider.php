<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Presentation\Providers;

use App\Modules\Optional\Reports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Optional\Reports\Application\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportGenerationDispatcher;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportRequestRecorder;
use App\Modules\Optional\Reports\Application\ReportExportGenerationProcess;
use App\Modules\Optional\Reports\Application\ReportExportLifecycle;
use App\Modules\Optional\Reports\Application\ReportExportProcessDispatcher;
use App\Modules\Optional\Reports\Infrastructure\Persistence\DatabaseReportExportRequestStore;
use App\Modules\Optional\Reports\Infrastructure\Runtime\ReportExportGenerationProcessHandler;
use Illuminate\Support\ServiceProvider;

final class ReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportExportRequestStore::class, DatabaseReportExportRequestStore::class);
        $this->app->bind(ReportExportRequestRecorder::class, ReportExportLifecycle::class);
        $this->app->bind(ReportExportGenerationDispatcher::class, ReportExportProcessDispatcher::class);
        $this->app->bind('reports.managed_process.export_generation_definition', fn () => ReportExportGenerationProcess::definition());

        $this->app->tag([ReportsPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag(['reports.managed_process.export_generation_definition'], 'atlas.managed_process_definitions');
        $this->app->tag([ReportExportGenerationProcessHandler::class], 'atlas.managed_process_handlers');
    }
}
