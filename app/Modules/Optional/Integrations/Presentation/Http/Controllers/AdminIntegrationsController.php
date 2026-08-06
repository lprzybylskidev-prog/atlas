<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Presentation\Http\Controllers;

use App\Modules\Optional\Integrations\Application\Contracts\IntegrationRegistry;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationDefinition;
use App\Modules\Optional\Integrations\Application\Public\Persistence\IntegrationsDatabaseTable;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableDefinition;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableResult;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminIntegrationsController
{
    public function __construct(
        private IntegrationRegistry $registry,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::INTEGRATION_ADAPTERS);
        $adapters = array_map(fn (IntegrationDefinition $definition): array => $this->integrationRow($definition), $this->registry->all());
        $filters = $this->filters($request, $adapters);
        $filteredAdapters = $this->filteredAdapters($adapters, $filters);
        $result = $this->tableResult($request, $definition, $filteredAdapters);
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;
        $runs = $this->recentRuns();

        return Inertia::render('Admin/Integrations/Index', [
            'integrations' => $result->rows,
            'summary' => $this->summary($adapters, $result->total),
            'filterOptions' => $this->filterOptions($adapters),
            'externalApiEnabled' => Config::boolean('atlas.integrations.external_api_enabled', false),
            'recentRuns' => $runs,
            'table' => $table,
        ]);
    }

    public function test(Request $request, string $integration): RedirectResponse
    {
        $adapter = $this->registry->get($integration);

        if ($adapter === null) {
            return redirect()->route('admin.integrations.index')->with('flash.messages', [
                FlashMessage::error('flash.integrations.adapter_not_found'),
            ]);
        }

        $result = $adapter->testConnection((string) Str::uuid());

        DB::table(IntegrationsDatabaseTable::CONNECTIONS)->updateOrInsert(
            ['integration_key' => $result->integrationKey],
            [
                'public_id' => (string) Str::ulid(),
                'name' => $adapter->definition()->name,
                'enabled' => false,
                'external_api_enabled' => $adapter->definition()->externalApiEnabled,
                'source_of_truth' => $adapter->definition()->sourceOfTruth,
                'last_success_at' => $result->successful ? $result->testedAt : null,
                'last_error_at' => $result->successful ? null : $result->testedAt,
                'last_error_message' => $result->successful ? null : $result->message,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return redirect()
            ->route('admin.integrations.index')
            ->with('flash.messages', [
                $result->successful
                    ? FlashMessage::success('flash.integrations.test_succeeded')
                    : FlashMessage::error('flash.integrations.test_failed'),
            ]);
    }

    /**
     * @return array<string, scalar|null|array<int, string>>
     */
    private function integrationRow(IntegrationDefinition $definition): array
    {
        $connection = DB::table(IntegrationsDatabaseTable::CONNECTIONS)->where('integration_key', $definition->key)->first();
        $circuit = DB::table(IntegrationsDatabaseTable::CIRCUIT_BREAKERS)->where('integration_key', $definition->key)->orderByDesc('updated_at')->first();
        $lastRun = DB::table(IntegrationsDatabaseTable::SYNC_RUNS)->where('integration_key', $definition->key)->orderByDesc('started_at')->first();

        return [
            'key' => $definition->key,
            'name' => $definition->name,
            'adapterClass' => $definition->adapterClass,
            'sourceOfTruth' => $definition->sourceOfTruth,
            'providedScopes' => $definition->providedScopes,
            'requiredModules' => $definition->requiredModules,
            'optionalModules' => $definition->optionalModules,
            'enabled' => (bool) ($connection->enabled ?? false),
            'externalApiEnabled' => Config::boolean('atlas.integrations.external_api_enabled', false) && (bool) ($connection->external_api_enabled ?? $definition->externalApiEnabled),
            'lastSuccessAt' => $this->string($connection->last_success_at ?? null),
            'lastErrorAt' => $this->string($connection->last_error_at ?? null),
            'lastErrorMessage' => $this->string($connection->last_error_message ?? null),
            'circuitState' => $this->string($circuit->state ?? 'closed'),
            'lastRunStatus' => $this->string($lastRun->status ?? null),
            'lastRunAt' => $this->string($lastRun->started_at ?? null),
        ];
    }

    /**
     * @param  list<array<string, scalar|array<int, string>|null>>  $rows
     */
    private function tableResult(Request $request, TableDefinition $definition, array $rows): TableResult
    {
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);

        return $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
    }

    /**
     * @param  list<array<string, scalar|array<int, string>|null>>  $rows
     * @return array{status: string, circuit: string, external_api: string, scope: string}
     */
    private function filters(Request $request, array $rows): array
    {
        return [
            'status' => $this->oneOf($request->query('status'), ['all', 'enabled', 'disabled']),
            'circuit' => $this->oneOf($request->query('circuit'), ['all', 'closed', 'open', 'half_open']),
            'external_api' => $this->oneOf($request->query('external_api'), ['all', 'enabled', 'disabled']),
            'scope' => $this->oneOf($request->query('scope'), $this->allOr($this->uniqueListValues($rows, 'providedScopes'))),
        ];
    }

    /**
     * @param  list<array<string, scalar|array<int, string>|null>>  $rows
     * @return array{scopes: list<string>}
     */
    private function filterOptions(array $rows): array
    {
        return [
            'scopes' => $this->uniqueListValues($rows, 'providedScopes'),
        ];
    }

    /**
     * @param  list<array<string, scalar|array<int, string>|null>>  $rows
     * @param  array{status: string, circuit: string, external_api: string, scope: string}  $filters
     * @return list<array<string, scalar|array<int, string>|null>>
     */
    private function filteredAdapters(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['status'] === 'enabled' && ($row['enabled'] ?? false) !== true) {
                return false;
            }

            if ($filters['status'] === 'disabled' && ($row['enabled'] ?? false) !== false) {
                return false;
            }

            if ($filters['circuit'] !== 'all' && ($row['circuitState'] ?? null) !== $filters['circuit']) {
                return false;
            }

            if ($filters['external_api'] === 'enabled' && ($row['externalApiEnabled'] ?? false) !== true) {
                return false;
            }

            if ($filters['external_api'] === 'disabled' && ($row['externalApiEnabled'] ?? false) !== false) {
                return false;
            }

            if ($filters['scope'] !== 'all') {
                $scopes = $row['providedScopes'] ?? [];

                if (! is_array($scopes) || ! in_array($filters['scope'], $scopes, true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, scalar|array<int, string>|null>>  $adapters
     * @return array{registered: int, visible: int, enabled: int, openCircuits: int, running: int, failedLastRuns: int}
     */
    private function summary(array $adapters, int $visible): array
    {
        return [
            'registered' => count($adapters),
            'visible' => $visible,
            'enabled' => count(array_filter($adapters, static fn (array $adapter): bool => ($adapter['enabled'] ?? false) === true)),
            'openCircuits' => (int) DB::table(IntegrationsDatabaseTable::CIRCUIT_BREAKERS)->where('state', 'open')->count(),
            'running' => (int) DB::table(IntegrationsDatabaseTable::SYNC_RUNS)->where('status', 'running')->count(),
            'failedLastRuns' => (int) DB::table(IntegrationsDatabaseTable::SYNC_RUNS)->where('status', 'failed')->where('started_at', '>=', now()->subDay())->count(),
        ];
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    private function recentRuns(): array
    {
        $runs = [];

        foreach (DB::table(IntegrationsDatabaseTable::SYNC_RUNS)
            ->orderByDesc('started_at')
            ->limit(8)
            ->get(['integration_key', 'operation', 'correlation_id', 'status', 'started_at', 'finished_at', 'message']) as $row) {
            $runs[] = [
                'integrationKey' => $this->string($row->integration_key ?? null),
                'operation' => $this->string($row->operation ?? null),
                'correlationId' => $this->string($row->correlation_id ?? null),
                'status' => $this->string($row->status ?? null),
                'startedAt' => $this->string($row->started_at ?? null),
                'finishedAt' => $this->string($row->finished_at ?? null),
                'message' => $this->string($row->message ?? null),
            ];
        }

        return $runs;
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  list<array<string, scalar|array<int, string>|null>>  $rows
     * @return list<string>
     */
    private function uniqueListValues(array $rows, string $key): array
    {
        $values = [];

        foreach ($rows as $row) {
            $items = $row[$key] ?? [];

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if ($item !== '') {
                    $values[] = $item;
                }
            }
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function allOr(array $values): array
    {
        return ['all', ...$values];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        $normalized = is_scalar($value) ? (string) $value : 'all';

        return in_array($normalized, $allowed, true) ? $normalized : 'all';
    }
}
