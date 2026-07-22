<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\Integrations\Application\Contracts\IntegrationRegistry;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationDefinition;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final readonly class AdminIntegrationAdaptersDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private IntegrationRegistry $registry) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::INTEGRATION_ADAPTERS;
    }

    public function tableName(): string
    {
        return 'Integration adapters';
    }

    public function owningModuleKey(): string
    {
        return 'integrations';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-integration-adapters-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'name' => 'Integration',
            'key' => 'Key',
            'sourceOfTruth' => 'Source of truth',
            'adapterClass' => 'Adapter',
            'circuitState' => 'Circuit',
            'lastSuccessAt' => 'Last success',
            'lastErrorAt' => 'Last error',
            'lastErrorMessage' => 'Last error message',
            'externalApiEnabled' => 'External API',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_map(fn (IntegrationDefinition $definition): array => $this->integrationRow($definition), $this->registry->all());

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
            yield $row;
        }
    }

    /**
     * @return array<string, scalar|\Stringable|null>
     */
    private function integrationRow(IntegrationDefinition $definition): array
    {
        $connection = DB::table(DatabaseTable::INTEGRATION_CONNECTIONS)->where('integration_key', $definition->key)->first();
        $circuit = DB::table(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS)->where('integration_key', $definition->key)->orderByDesc('updated_at')->first();

        return [
            'key' => $definition->key,
            'name' => $definition->name,
            'adapterClass' => $definition->adapterClass,
            'sourceOfTruth' => $definition->sourceOfTruth,
            'externalApiEnabled' => Config::boolean('atlas.integrations.external_api_enabled', false) && (bool) ($connection->external_api_enabled ?? $definition->externalApiEnabled),
            'lastSuccessAt' => self::stringValue($connection->last_success_at ?? null),
            'lastErrorAt' => self::stringValue($connection->last_error_at ?? null),
            'lastErrorMessage' => self::stringValue($connection->last_error_message ?? null),
            'circuitState' => self::stringValue($circuit->state ?? 'closed'),
        ];
    }
}
