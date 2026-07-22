<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\DTOs\ReportChartDefinition;
use App\Modules\Core\Exports\Application\DTOs\ReportChartPoint;
use App\Modules\Core\Exports\Application\DTOs\ReportChartSeries;
use App\Modules\Core\Exports\Application\DTOs\ReportExportColumn;
use App\Modules\Core\Exports\Application\DTOs\ReportExportTotal;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use Illuminate\Contracts\View\Factory as ViewFactory;

final readonly class ReportHtmlDocumentFactory
{
    public function __construct(
        private TabularReportExportData $table,
        private ViewFactory $views,
        private ReportFontCss $fonts,
        private ReportLayoutConfigurationProvider $configuration,
        private ReportChartProviderRegistry $charts,
    ) {}

    public function tableReport(ReportExportGenerationRequest $request, bool $browserPrint = false): string
    {
        $columns = $this->table->columns($request);
        $configuration = $this->configuration->get();
        $rows = iterator_to_array($this->table->rows($request, $columns), false);
        $totals = $this->table->totals(count($rows));
        $charts = $this->charts->has($request->reportKey)
            ? $this->charts->get($request->reportKey)->charts($request)
            : [];

        return $this->views->make('reports.table', [
            'reportName' => $request->reportName,
            'browserPrint' => $browserPrint,
            'companyName' => $configuration->companyName,
            'footerText' => $configuration->footerText,
            'logoDataUri' => $configuration->logoDataUri,
            'moduleKey' => $request->moduleKey,
            'activeTeamPublicId' => $request->activeTeamPublicId,
            'requestingUserPublicId' => $request->requestingUserPublicId,
            'generatedAt' => now('UTC')->format('Y-m-d H:i:s T'),
            'releaseVersion' => $request->releaseVersion,
            'ruleVersion' => $request->ruleVersion,
            'fontCss' => $this->fonts->css(),
            'filters' => $request->filters,
            'timeRange' => $request->timeRange,
            'columns' => array_map(static fn (ReportExportColumn $column): array => [
                'key' => $column->key,
                'label' => $column->label,
            ], $columns),
            'rows' => $rows,
            'totals' => array_map(static fn (ReportExportTotal $total): array => [
                'label' => $total->label,
                'value' => $total->value,
            ], $totals),
            'charts' => array_map(static fn (ReportChartDefinition $chart): array => [
                'key' => $chart->key,
                'title' => $chart->title,
                'description' => $chart->description,
                'unit' => $chart->unit,
                'series' => array_map(static fn (ReportChartSeries $series): array => [
                    'label' => $series->label,
                    'points' => array_map(static fn (ReportChartPoint $point): array => [
                        'label' => $point->label,
                        'value' => $point->value,
                    ], $series->points),
                ], $chart->series),
            ], $charts),
        ])->render();
    }
}
