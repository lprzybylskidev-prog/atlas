<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\ReportChartProvider;

final class ReportChartProviderRegistry
{
    /**
     * @var array<string, ReportChartProvider>
     */
    private array $providers = [];

    /**
     * @param  iterable<ReportChartProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->reportKey()] = $provider;
        }
    }

    public function has(string $reportKey): bool
    {
        return isset($this->providers[$reportKey]);
    }

    public function get(string $reportKey): ReportChartProvider
    {
        return $this->providers[$reportKey] ?? throw new \RuntimeException(sprintf('Report chart provider for [%s] is not registered.', $reportKey));
    }
}
