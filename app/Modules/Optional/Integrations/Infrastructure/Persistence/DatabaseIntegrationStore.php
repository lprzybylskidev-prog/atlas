<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\Integrations\Application\Exceptions\ExternalApiAccessDisabled;
use App\Modules\Optional\Integrations\Application\Public\Contracts\ExternalApiAccessPolicy;
use App\Modules\Optional\Integrations\Application\Public\Contracts\ExternalIdMappingStore;
use App\Modules\Optional\Integrations\Application\Public\Contracts\IntegrationIdempotencyStore;
use App\Modules\Optional\Integrations\Application\Public\Contracts\SynchronizationHistory;
use App\Modules\Optional\Integrations\Application\Public\DTOs\ExternalCredentialPolicy;
use App\Modules\Optional\Integrations\Application\Public\DTOs\ExternalIdMapping;
use App\Modules\Optional\Integrations\Application\Public\Persistence\IntegrationsDatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;

final readonly class DatabaseIntegrationStore implements ExternalApiAccessPolicy, ExternalIdMappingStore, IntegrationIdempotencyStore, SynchronizationHistory
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditRecorder $audit,
    ) {}

    public function assertExternalApiEnabled(ExternalCredentialPolicy $policy, string $moduleKey, string $scope): void
    {
        $enabled = Config::boolean('atlas.integrations.external_api_enabled', false)
            && $policy->externalApiEnabled
            && in_array($scope, $policy->scopes, true)
            && ($policy->allowedModules === [] || in_array($moduleKey, $policy->allowedModules, true));

        if ($policy->expiresAt !== null && $policy->expiresAt->isPast()) {
            $enabled = false;
        }

        if (! $enabled) {
            $this->audit('integration.external_api_blocked', 'rejected', null, $policy->teamId, $moduleKey, [
                'client_key' => $policy->clientKey,
                'scope' => $scope,
            ]);

            throw ExternalApiAccessDisabled::forScope($moduleKey, $scope);
        }
    }

    public function map(ExternalIdMapping $mapping, ?int $actorId = null): void
    {
        $this->db->table(IntegrationsDatabaseTable::EXTERNAL_ID_MAPPINGS)->upsert([
            [
                'integration_key' => $mapping->integrationKey,
                'source_system' => $mapping->sourceSystem,
                'entity_type' => $mapping->entityType,
                'external_id' => $mapping->externalId,
                'internal_public_id' => $mapping->internalPublicId,
                'team_id' => $mapping->teamId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['integration_key', 'source_system', 'entity_type', 'external_id', 'team_id'], ['internal_public_id', 'updated_at']);

        $this->audit('integration.external_id_mapped', 'succeeded', $actorId, $mapping->teamId, $mapping->integrationKey, [
            'source_system' => $mapping->sourceSystem,
            'entity_type' => $mapping->entityType,
        ]);
    }

    public function findInternalPublicId(string $integrationKey, string $sourceSystem, string $entityType, string $externalId, ?int $teamId = null): ?string
    {
        $value = $this->db->table(IntegrationsDatabaseTable::EXTERNAL_ID_MAPPINGS)
            ->where('integration_key', $integrationKey)
            ->where('source_system', $sourceSystem)
            ->where('entity_type', $entityType)
            ->where('external_id', $externalId)
            ->where('team_id', $teamId)
            ->value('internal_public_id');

        return is_scalar($value) ? (string) $value : null;
    }

    public function begin(string $integrationKey, string $operation, string $idempotencyKey, string $requestHash, ?int $teamId = null): bool
    {
        $inserted = $this->db->table(IntegrationsDatabaseTable::IDEMPOTENCY_KEYS)->insertOrIgnore([
            'integration_key' => $integrationKey,
            'operation' => $operation,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'team_id' => $teamId,
            'completed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted === 1;
    }

    public function complete(string $integrationKey, string $operation, string $idempotencyKey, bool $successful, array $responseSummary = []): void
    {
        $this->db->table(IntegrationsDatabaseTable::IDEMPOTENCY_KEYS)
            ->where('integration_key', $integrationKey)
            ->where('operation', $operation)
            ->where('idempotency_key', $idempotencyKey)
            ->update([
                'completed' => true,
                'successful' => $successful,
                'response_summary' => $this->json($responseSummary),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function start(string $integrationKey, string $operation, string $correlationId, ?int $teamId = null, array $metadata = []): int
    {
        return (int) $this->db->table(IntegrationsDatabaseTable::SYNC_RUNS)->insertGetId([
            'integration_key' => $integrationKey,
            'operation' => $operation,
            'correlation_id' => $correlationId,
            'team_id' => $teamId,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => $this->json($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function finish(int $runId, string $status, ?string $message = null, array $metadata = []): void
    {
        $this->db->table(IntegrationsDatabaseTable::SYNC_RUNS)->where('id', $runId)->update([
            'status' => $status,
            'finished_at' => now(),
            'message' => $message,
            'metadata' => $this->json($metadata),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    private function audit(string $action, string $result, ?int $actorId, ?int $teamId, string $entityPublicId, array $context = []): void
    {
        $actorPublicId = $actorId === null ? null : $this->publicId(IdentityDatabaseTable::USERS, $actorId);
        $teamPublicId = $teamId === null ? null : $this->publicId(TeamsDatabaseTable::TEAMS, $teamId);

        $this->audit->record(new AuditEvent(
            module: 'integrations',
            action: $action,
            result: $result,
            source: app()->runningInConsole() ? 'system' : 'http',
            actorPublicId: $actorPublicId,
            targetType: 'integration',
            targetPublicId: $entityPublicId,
            aggregateType: 'integration',
            aggregatePublicId: $entityPublicId,
            teamPublicId: $teamPublicId,
            metadata: $context,
            security: true,
            securityCategory: SecurityAuditCategory::Integrations,
        ));
    }

    private function publicId(string $table, int $id): ?string
    {
        $value = $this->db->table($table)->where('id', $id)->value('public_id');

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, scalar|null>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
