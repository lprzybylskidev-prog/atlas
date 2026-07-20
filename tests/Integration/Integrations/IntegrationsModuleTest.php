<?php

declare(strict_types=1);

namespace Tests\Integration\Integrations;

use App\Modules\Optional\Integrations\Application\Contracts\IntegrationAdapter;
use App\Modules\Optional\Integrations\Application\Contracts\IntegrationRegistry;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationDefinition;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationRetryPolicy;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationTestResult;
use App\Modules\Optional\Integrations\Application\Enums\IntegrationCircuitState;
use App\Modules\Optional\Integrations\Application\Exceptions\ExternalApiAccessDisabled;
use App\Modules\Optional\Integrations\Application\Public\Contracts\ExternalApiAccessPolicy;
use App\Modules\Optional\Integrations\Application\Public\Contracts\ExternalIdMappingStore;
use App\Modules\Optional\Integrations\Application\Public\Contracts\IntegrationIdempotencyStore;
use App\Modules\Optional\Integrations\Application\Public\DTOs\ExternalCredentialPolicy;
use App\Modules\Optional\Integrations\Application\Public\DTOs\ExternalIdMapping;
use App\Modules\Optional\Integrations\Infrastructure\Runtime\IntegrationOperationRunner;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

final class IntegrationsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_api_access_is_disabled_by_default(): void
    {
        Config::set('atlas.integrations.external_api_enabled', false);

        $this->expectException(ExternalApiAccessDisabled::class);

        $this->app->make(ExternalApiAccessPolicy::class)->assertExternalApiEnabled(new ExternalCredentialPolicy(
            clientKey: 'collector-import',
            scopes: ['cases.write'],
            allowedModules: ['imports'],
            externalApiEnabled: true,
        ), 'imports', 'cases.write');
    }

    public function test_external_id_mapping_and_idempotency_are_persisted(): void
    {
        $this->app->make(ExternalIdMappingStore::class)->map(new ExternalIdMapping(
            integrationKey: 'dialer',
            sourceSystem: 'dialer-api',
            entityType: 'debtor',
            externalId: 'EXT-123',
            internalPublicId: '01J00000000000000000000001',
        ));

        self::assertSame(
            '01J00000000000000000000001',
            $this->app->make(ExternalIdMappingStore::class)->findInternalPublicId('dialer', 'dialer-api', 'debtor', 'EXT-123'),
        );

        $store = $this->app->make(IntegrationIdempotencyStore::class);

        self::assertTrue($store->begin('dialer', 'push-call-result', 'idem-1', str_repeat('a', 64)));
        self::assertFalse($store->begin('dialer', 'push-call-result', 'idem-1', str_repeat('a', 64)));

        $store->complete('dialer', 'push-call-result', 'idem-1', true, ['accepted' => true]);

        $this->assertDatabaseHas(DatabaseTable::INTEGRATION_IDEMPOTENCY_KEYS, [
            'integration_key' => 'dialer',
            'operation' => 'push-call-result',
            'idempotency_key' => 'idem-1',
            'completed' => true,
            'successful' => true,
        ]);
    }

    public function test_operation_runner_records_failures_and_opens_circuit_breaker(): void
    {
        $this->activateIntegrations();

        $result = $this->app->make(IntegrationOperationRunner::class)->run(
            integrationKey: 'dialer',
            operationName: 'pull-calls',
            operation: static function (string $_correlationId): array {
                throw new RuntimeException('Timeout from dialer.');
            },
            policy: new IntegrationRetryPolicy(maxAttempts: 2, baseDelayMilliseconds: 0, circuitFailureThreshold: 1),
        );

        self::assertFalse($result->successful);
        self::assertSame(2, $result->attempts);

        $this->assertDatabaseHas(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS, [
            'integration_key' => 'dialer',
            'operation' => 'pull-calls',
            'state' => IntegrationCircuitState::Open->value,
        ]);
        $this->assertDatabaseHas(DatabaseTable::INTEGRATION_SYNC_RUNS, [
            'integration_key' => 'dialer',
            'operation' => 'pull-calls',
            'status' => 'failed',
        ]);
    }

    public function test_operation_runner_treats_timeout_as_failed_execution(): void
    {
        $this->activateIntegrations();

        $result = $this->app->make(IntegrationOperationRunner::class)->run(
            integrationKey: 'crm',
            operationName: 'pull-leads',
            operation: static function (string $_correlationId): array {
                usleep(2000);

                return ['received' => 1];
            },
            policy: new IntegrationRetryPolicy(maxAttempts: 1, baseDelayMilliseconds: 0, timeoutMilliseconds: 1, circuitFailureThreshold: 1),
        );

        self::assertFalse($result->successful);
        self::assertSame('Integration operation timed out.', $result->errorMessage);

        $this->assertDatabaseHas(DatabaseTable::INTEGRATION_SYNC_RUNS, [
            'integration_key' => 'crm',
            'operation' => 'pull-leads',
            'status' => 'failed',
        ]);
    }

    public function test_configured_registry_exposes_adapter_definitions(): void
    {
        Config::set('atlas.integrations.adapters', [SuccessfulFakeIntegrationAdapter::class]);

        $definitions = $this->app->make(IntegrationRegistry::class)->all();

        self::assertCount(1, $definitions);
        self::assertSame('dialer', $definitions[0]->key);
        self::assertFalse($definitions[0]->externalApiEnabled);
    }

    private function activateIntegrations(): void
    {
        $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
            moduleKey: 'integrations',
            scope: ModuleActivationScope::Global,
            enabled: true,
            reason: 'Integration test setup',
            source: ModuleActivationSource::Manual,
        ));
    }
}

final class SuccessfulFakeIntegrationAdapter implements IntegrationAdapter
{
    public function definition(): IntegrationDefinition
    {
        return new IntegrationDefinition(
            key: 'dialer',
            name: 'Dialer',
            adapterClass: self::class,
            sourceOfTruth: 'Atlas owns collection case state; dialer owns call attempt telemetry.',
            providedScopes: ['calls.read'],
        );
    }

    public function testConnection(string $correlationId): IntegrationTestResult
    {
        return new IntegrationTestResult(
            integrationKey: 'dialer',
            successful: true,
            message: 'Dialer connection is healthy.',
            testedAt: CarbonImmutable::now('UTC'),
            metadata: ['correlation_id' => $correlationId],
        );
    }
}
