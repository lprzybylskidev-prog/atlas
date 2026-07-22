<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Presentation\Providers;

use App\Modules\Optional\Reports\Application\Contracts\ReportChartProvider;
use App\Modules\Optional\Reports\Application\Contracts\ReportExportDataProvider;
use App\Modules\Optional\Reports\Application\Contracts\ReportExportGenerator;
use App\Modules\Optional\Reports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Optional\Reports\Application\Contracts\ReportPdfRenderer;
use App\Modules\Optional\Reports\Application\Contracts\ReportRenderReadinessProbe;
use App\Modules\Optional\Reports\Application\CsvReportExportGenerator;
use App\Modules\Optional\Reports\Application\PdfReportExportGenerator;
use App\Modules\Optional\Reports\Application\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportArtifactAccess;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportGenerationDispatcher;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportMaintenance;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportRequestRecorder;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportPrintViewAccess;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportRenderCredentialAccess;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportRenderCredentialIssuer;
use App\Modules\Optional\Reports\Application\ReportChartProviderRegistry;
use App\Modules\Optional\Reports\Application\ReportExportArtifactService;
use App\Modules\Optional\Reports\Application\ReportExportDataProviderRegistry;
use App\Modules\Optional\Reports\Application\ReportExportGenerationProcess;
use App\Modules\Optional\Reports\Application\ReportExportGeneratorRegistry;
use App\Modules\Optional\Reports\Application\ReportExportLifecycle;
use App\Modules\Optional\Reports\Application\ReportExportProcessDispatcher;
use App\Modules\Optional\Reports\Application\ReportPrintViewService;
use App\Modules\Optional\Reports\Application\ReportRenderCredentialService;
use App\Modules\Optional\Reports\Application\ReportRenderReadinessRegistry;
use App\Modules\Optional\Reports\Application\ReportsDeactivationGuard;
use App\Modules\Optional\Reports\Application\XlsxReportExportGenerator;
use App\Modules\Optional\Reports\Infrastructure\Persistence\DatabaseReportExportRequestStore;
use App\Modules\Optional\Reports\Infrastructure\Rendering\PlaywrightReportPdfRenderer;
use App\Modules\Optional\Reports\Infrastructure\Runtime\ReportExportGenerationProcessHandler;
use App\Modules\Optional\Reports\Presentation\Console\CleanupExpiredReportExportsCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ReportsServiceProvider extends ServiceProvider
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
        $this->app->bind(ReportExportGeneratorRegistry::class, fn (Application $app) => new ReportExportGeneratorRegistry($this->reportExportGenerators($app)));
        $this->app->bind(ReportRenderReadinessRegistry::class, fn (Application $app) => new ReportRenderReadinessRegistry($this->reportRenderReadinessProbes($app)));
        $this->app->bind('reports.managed_process.export_generation_definition', fn () => ReportExportGenerationProcess::definition());

        $this->app->tag([ReportsPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([CsvReportExportGenerator::class, XlsxReportExportGenerator::class, PdfReportExportGenerator::class], 'atlas.report_export_generators');
        $this->app->tag([ReportsDeactivationGuard::class], 'atlas.module_deactivation_guards');
        $this->app->tag(['reports.managed_process.export_generation_definition'], 'atlas.managed_process_definitions');
        $this->app->tag([ReportExportGenerationProcessHandler::class], 'atlas.managed_process_handlers');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CleanupExpiredReportExportsCommand::class]);
        }
    }

    /**
     * @return list<ReportChartProvider>
     */
    private function reportChartProviders(Application $app): array
    {
        $providers = [];

        foreach ($app->tagged('atlas.report_chart_providers') as $provider) {
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

        foreach ($app->tagged('atlas.report_data_providers') as $provider) {
            if (! $provider instanceof ReportExportDataProvider) {
                throw new \RuntimeException('Tagged report data provider must implement ReportExportDataProvider.');
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

        foreach ($app->tagged('atlas.report_export_generators') as $generator) {
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

        foreach ($app->tagged('atlas.report_render_readiness_probes') as $probe) {
            if (! $probe instanceof ReportRenderReadinessProbe) {
                throw new \RuntimeException('Tagged report render readiness probe must implement ReportRenderReadinessProbe.');
            }

            $probes[] = $probe;
        }

        return $probes;
    }
}
