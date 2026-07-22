<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\ReportExportDataProvider;
use App\Modules\Core\Exports\Application\Exceptions\ReportExportDataProviderMissing;

final class ReportExportDataProviderRegistry
{
    /**
     * @var array<string, ReportExportDataProvider>
     */
    private array $providers = [];

    /**
     * @param  iterable<ReportExportDataProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->reportKey()] = $provider;
        }
    }

    public function get(string $reportKey): ReportExportDataProvider
    {
        return $this->providers[$reportKey] ?? throw ReportExportDataProviderMissing::forReport($reportKey);
    }
}
