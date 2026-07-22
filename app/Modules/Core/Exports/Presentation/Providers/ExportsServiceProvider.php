<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Presentation\Providers;

use App\Modules\Core\Exports\Application\AdminDataTableExportProviderRegistry;
use App\Modules\Core\Exports\Application\Contracts\AdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Contracts\ReportChartProvider;
use App\Modules\Core\Exports\Application\Contracts\ReportExportDataProvider;
use App\Modules\Core\Exports\Application\Contracts\ReportExportGenerator;
use App\Modules\Core\Exports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Core\Exports\Application\Contracts\ReportPdfRenderer;
use App\Modules\Core\Exports\Application\Contracts\ReportRenderReadinessProbe;
use App\Modules\Core\Exports\Application\CsvReportExportGenerator;
use App\Modules\Core\Exports\Application\ExportsDeactivationGuard;
use App\Modules\Core\Exports\Application\PdfReportExportGenerator;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportArtifactAccess;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportGenerationDispatcher;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportMaintenance;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportRequestRecorder;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportPrintViewAccess;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportRenderCredentialAccess;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportRenderCredentialIssuer;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Exports\Application\ReportChartProviderRegistry;
use App\Modules\Core\Exports\Application\ReportExportArtifactService;
use App\Modules\Core\Exports\Application\ReportExportDataProviderRegistry;
use App\Modules\Core\Exports\Application\ReportExportGenerationProcess;
use App\Modules\Core\Exports\Application\ReportExportGeneratorRegistry;
use App\Modules\Core\Exports\Application\ReportExportLifecycle;
use App\Modules\Core\Exports\Application\ReportExportProcessDispatcher;
use App\Modules\Core\Exports\Application\ReportPrintViewService;
use App\Modules\Core\Exports\Application\ReportRenderCredentialService;
use App\Modules\Core\Exports\Application\ReportRenderReadinessRegistry;
use App\Modules\Core\Exports\Application\XlsxReportExportGenerator;
use App\Modules\Core\Exports\Infrastructure\Persistence\DatabaseReportExportRequestStore;
use App\Modules\Core\Exports\Infrastructure\Rendering\PlaywrightReportPdfRenderer;
use App\Modules\Core\Exports\Infrastructure\Runtime\ReportExportGenerationProcessHandler;
use App\Modules\Core\Exports\Presentation\Console\CleanupExpiredExportsCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ExportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportExportRequestStore::class, DatabaseReportExportRequestStore::class);
        $this->app->bind(ReportExportRequestRecorder::class, ReportExportLifecycle::class);
        $this->app->bind(ReportExportGenerationDispatcher::class, ReportExportProcessDispatcher::class);
        $this->app->bind(ReportExportArtifactAccess::class, ReportExportArtifactService::class);
        $this->app->bind(ReportExportMaintenance::class, ReportExportArtifactService::class);
        $this->app->bind(ReportPrintViewAccess::class, ReportPrintViewService::class);
        $this->app->bind(ReportRenderCredentialAccess::class, ReportRenderCredentialService::class);
        $this->app->bind(ReportRenderCredentialIssuer::class, ReportRenderCredentialService::class);
        $this->app->bind(ReportPdfRenderer::class, PlaywrightReportPdfRenderer::class);
        $this->app->bind(ReportChartProviderRegistry::class, fn (Application $app) => new ReportChartProviderRegistry($this->reportChartProviders($app)));
        $this->app->bind(ReportExportDataProviderRegistry::class, fn (Application $app) => new ReportExportDataProviderRegistry($this->reportDataProviders($app)));
        $this->app->bind(AdminDataTableExportProviderRegistry::class, fn (Application $app) => new AdminDataTableExportProviderRegistry($this->adminDataTableProviders($app)));
        $this->app->bind(ReportExportGeneratorRegistry::class, fn (Application $app) => new ReportExportGeneratorRegistry($this->reportExportGenerators($app)));
        $this->app->bind(ReportRenderReadinessRegistry::class, fn (Application $app) => new ReportRenderReadinessRegistry($this->reportRenderReadinessProbes($app)));
        $this->app->bind('exports.managed_process.generation_definition', fn () => ReportExportGenerationProcess::definition());

        $this->app->tag([ReportsPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([CsvReportExportGenerator::class, XlsxReportExportGenerator::class, PdfReportExportGenerator::class], 'atlas.export_generators');
        $this->app->tag([ExportsDeactivationGuard::class], 'atlas.module_deactivation_guards');
        $this->app->tag(['exports.managed_process.generation_definition'], 'atlas.managed_process_definitions');
        $this->app->tag([ReportExportGenerationProcessHandler::class], 'atlas.managed_process_handlers');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CleanupExpiredExportsCommand::class]);
        }
    }

    /**
     * @return list<ReportChartProvider>
     */
    private function reportChartProviders(Application $app): array
    {
        $providers = [];

        foreach ($app->tagged('atlas.export_chart_providers') as $provider) {
            if (! $provider instanceof ReportChartProvider) {
                throw new \RuntimeException('Tagged report chart provider must implement ReportChartProvider.');
            }

            $providers[] = $provider;
        }

        return $providers;
    }

    /**
     * @return list<ReportExportDataProvider>
     */
    private function reportDataProviders(Application $app): array
    {
        $providers = [];

        foreach ($this->adminDataTableProviders($app) as $provider) {
            $providers[] = $provider;
        }

        foreach ($app->tagged('atlas.export_data_providers') as $provider) {
            if (! $provider instanceof ReportExportDataProvider) {
                throw new \RuntimeException('Tagged report data provider must implement ReportExportDataProvider.');
            }

            $providers[] = $provider;
        }

        return $providers;
    }

    /**
     * @return list<AdminDataTableExportProvider>
     */
    private function adminDataTableProviders(Application $app): array
    {
        $providers = [];

        foreach ($app->tagged('atlas.admin_data_table_export_providers') as $provider) {
            if (! $provider instanceof AdminDataTableExportProvider) {
                throw new \RuntimeException('Tagged Admin DataTable export provider must implement AdminDataTableExportProvider.');
            }

            $providers[] = $provider;
        }

        return $providers;
    }

    /**
     * @return list<ReportExportGenerator>
     */
    private function reportExportGenerators(Application $app): array
    {
        $generators = [];

        foreach ($app->tagged('atlas.export_generators') as $generator) {
            if (! $generator instanceof ReportExportGenerator) {
                throw new \RuntimeException('Tagged report export generator must implement ReportExportGenerator.');
            }

            $generators[] = $generator;
        }

        return $generators;
    }

    /**
     * @return list<ReportRenderReadinessProbe>
     */
    private function reportRenderReadinessProbes(Application $app): array
    {
        $probes = [];

        foreach ($app->tagged('atlas.export_render_readiness_probes') as $probe) {
            if (! $probe instanceof ReportRenderReadinessProbe) {
                throw new \RuntimeException('Tagged report render readiness probe must implement ReportRenderReadinessProbe.');
            }

            $probes[] = $probe;
        }

        return $probes;
    }
}
