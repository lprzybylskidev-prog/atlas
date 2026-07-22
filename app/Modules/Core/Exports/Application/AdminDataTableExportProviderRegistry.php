<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\AdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Exceptions\ReportExportDataProviderMissing;

final class AdminDataTableExportProviderRegistry
{
    /**
     * @var array<string, AdminDataTableExportProvider>
     */
    private array $providers = [];

    /**
     * @param  iterable<AdminDataTableExportProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->tableKey()] = $provider;
        }
    }

    public function get(string $tableKey): AdminDataTableExportProvider
    {
        return $this->providers[$tableKey] ?? throw ReportExportDataProviderMissing::forReport($tableKey);
    }

    /**
     * @return list<string>
     */
    public function tableKeys(): array
    {
        return array_keys($this->providers);
    }
}
