<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Presentation\Http\Controllers;

use App\Modules\Optional\Integrations\Application\Contracts\IntegrationRegistry;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationDefinition;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminIntegrationsController
{
    public function __construct(private IntegrationRegistry $registry) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Integrations/Index', [
            'integrations' => array_map(fn (IntegrationDefinition $definition): array => $this->integrationRow($definition), $this->registry->all()),
            'summary' => $this->summary(),
            'externalApiEnabled' => Config::boolean('atlas.integrations.external_api_enabled', false),
            'recentRuns' => $this->recentRuns(),
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }

    public function test(Request $request, string $integration): RedirectResponse
    {
        $adapter = $this->registry->get($integration);

        if ($adapter === null) {
            return redirect()->route('admin.integrations.index')->with('error', 'Integration adapter was not found.');
        }

        $result = $adapter->testConnection((string) Str::uuid());

        DB::table(DatabaseTable::INTEGRATION_CONNECTIONS)->updateOrInsert(
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
            ->with($result->successful ? 'success' : 'error', $result->message);
    }

    /**
     * @return array<string, scalar|null|array<int, string>>
     */
    private function integrationRow(IntegrationDefinition $definition): array
    {
        $connection = DB::table(DatabaseTable::INTEGRATION_CONNECTIONS)->where('integration_key', $definition->key)->first();
        $circuit = DB::table(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS)->where('integration_key', $definition->key)->orderByDesc('updated_at')->first();
        $lastRun = DB::table(DatabaseTable::INTEGRATION_SYNC_RUNS)->where('integration_key', $definition->key)->orderByDesc('started_at')->first();

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
     * @return array{registered: int, openCircuits: int, running: int, failedLastRuns: int}
     */
    private function summary(): array
    {
        return [
            'registered' => count($this->registry->all()),
            'openCircuits' => (int) DB::table(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS)->where('state', 'open')->count(),
            'running' => (int) DB::table(DatabaseTable::INTEGRATION_SYNC_RUNS)->where('status', 'running')->count(),
            'failedLastRuns' => (int) DB::table(DatabaseTable::INTEGRATION_SYNC_RUNS)->where('status', 'failed')->where('started_at', '>=', now()->subDay())->count(),
        ];
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    private function recentRuns(): array
    {
        $runs = [];

        foreach (DB::table(DatabaseTable::INTEGRATION_SYNC_RUNS)
            ->orderByDesc('started_at')
            ->limit(20)
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
}
