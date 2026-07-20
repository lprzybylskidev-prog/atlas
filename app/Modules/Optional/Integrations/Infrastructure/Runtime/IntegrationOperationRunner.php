<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Infrastructure\Runtime;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationExecutionResult;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationRetryPolicy;
use App\Modules\Optional\Integrations\Application\Enums\IntegrationCircuitState;
use App\Modules\Optional\Integrations\Application\Enums\IntegrationRunStatus;
use App\Modules\Optional\Integrations\Application\Public\Contracts\IntegrationIdempotencyStore;
use App\Modules\Optional\Integrations\Application\Public\Contracts\SynchronizationHistory;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class IntegrationOperationRunner
{
    public function __construct(
        private ConnectionInterface $db,
        private OperationalModuleGuard $modules,
        private IntegrationIdempotencyStore $idempotency,
        private SynchronizationHistory $history,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  Closure(string): array<string, scalar|null>  $operation
     */
    public function run(
        string $integrationKey,
        string $operationName,
        Closure $operation,
        ?string $idempotencyKey = null,
        ?int $teamId = null,
        ?string $activeTeamPublicId = null,
        ?string $userPublicId = null,
        ?IntegrationRetryPolicy $policy = null,
    ): IntegrationExecutionResult {
        $this->modules->ensureAllowed('integrations', $activeTeamPublicId, $userPublicId);

        $policy ??= $this->defaultPolicy();
        $correlationId = (string) Str::uuid();
        $requestHash = hash('sha256', $integrationKey.'|'.$operationName.'|'.($idempotencyKey ?? $correlationId));

        if ($idempotencyKey !== null && ! $this->idempotency->begin($integrationKey, $operationName, $idempotencyKey, $requestHash, $teamId)) {
            $this->audit($integrationKey, 'integration.idempotency_replayed', 'skipped', $teamId, ['operation' => $operationName]);

            return new IntegrationExecutionResult(false, 1, $correlationId, $idempotencyKey, 'Duplicate idempotency key.');
        }

        $runId = $this->history->start($integrationKey, $operationName, $correlationId, $teamId);

        try {
            $this->assertCircuitAllows($integrationKey, $operationName);

            for ($attempt = 1; $attempt <= $policy->maxAttempts; $attempt++) {
                try {
                    $startedAt = microtime(true);
                    $metadata = $operation($correlationId);
                    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

                    if ($durationMs > $policy->timeoutMilliseconds) {
                        throw new RuntimeException('Integration operation timed out.');
                    }

                    $this->recordSuccess($integrationKey, $operationName);
                    $this->history->finish($runId, IntegrationRunStatus::Succeeded->value, metadata: [
                        ...$metadata,
                        'duration_ms' => $durationMs,
                    ]);

                    if ($idempotencyKey !== null) {
                        $this->idempotency->complete($integrationKey, $operationName, $idempotencyKey, true, $metadata);
                    }

                    $this->audit($integrationKey, 'integration.operation_succeeded', 'succeeded', $teamId, [
                        'operation' => $operationName,
                        'attempts' => $attempt,
                        'correlation_id' => $correlationId,
                        'duration_ms' => $durationMs,
                    ]);

                    return new IntegrationExecutionResult(true, $attempt, $correlationId, $idempotencyKey, metadata: [
                        ...$metadata,
                        'duration_ms' => $durationMs,
                    ]);
                } catch (Throwable $exception) {
                    if ($attempt >= $policy->maxAttempts) {
                        throw $exception;
                    }

                    if ($policy->baseDelayMilliseconds > 0) {
                        usleep($policy->baseDelayMilliseconds * 1000 * $attempt);
                    }
                }
            }
        } catch (Throwable $exception) {
            $this->recordFailure($integrationKey, $operationName, $exception->getMessage(), $policy);
            $this->history->finish($runId, IntegrationRunStatus::Failed->value, $exception->getMessage());

            if ($idempotencyKey !== null) {
                $this->idempotency->complete($integrationKey, $operationName, $idempotencyKey, false, ['error' => $exception->getMessage()]);
            }

            $this->audit($integrationKey, 'integration.operation_failed', 'failed', $teamId, [
                'operation' => $operationName,
                'correlation_id' => $correlationId,
            ]);

            return new IntegrationExecutionResult(false, $policy->maxAttempts, $correlationId, $idempotencyKey, $exception->getMessage());
        }

        throw new RuntimeException('Integration operation finished without a result.');
    }

    private function assertCircuitAllows(string $integrationKey, string $operationName): void
    {
        $row = $this->db->table(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS)
            ->where('integration_key', $integrationKey)
            ->where('operation', $operationName)
            ->first();

        if (! is_object($row) || ($row->state ?? null) !== IntegrationCircuitState::Open->value) {
            return;
        }

        $openedUntil = $row->opened_until ?? null;

        if (is_scalar($openedUntil) && now()->greaterThan((string) $openedUntil)) {
            $this->db->table(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS)->where('id', $row->id)->update([
                'state' => IntegrationCircuitState::HalfOpen->value,
                'updated_at' => now(),
            ]);

            return;
        }

        throw new RuntimeException('Integration circuit breaker is open.');
    }

    private function recordSuccess(string $integrationKey, string $operationName): void
    {
        $this->db->table(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS)->upsert([
            [
                'integration_key' => $integrationKey,
                'operation' => $operationName,
                'state' => IntegrationCircuitState::Closed->value,
                'failure_count' => 0,
                'opened_until' => null,
                'last_success_at' => now(),
                'last_error_message' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['integration_key', 'operation'], ['state', 'failure_count', 'opened_until', 'last_success_at', 'last_error_message', 'updated_at']);

        $this->db->table(DatabaseTable::INTEGRATION_CONNECTIONS)->where('integration_key', $integrationKey)->update([
            'last_success_at' => now(),
            'last_error_message' => null,
            'updated_at' => now(),
        ]);
    }

    private function defaultPolicy(): IntegrationRetryPolicy
    {
        return new IntegrationRetryPolicy(
            maxAttempts: Config::integer('atlas.integrations.retry.max_attempts', 3),
            baseDelayMilliseconds: Config::integer('atlas.integrations.retry.base_delay_milliseconds', 100),
            timeoutMilliseconds: Config::integer('atlas.integrations.retry.timeout_milliseconds', 5000),
            circuitFailureThreshold: Config::integer('atlas.integrations.retry.circuit_failure_threshold', 3),
            circuitOpenSeconds: Config::integer('atlas.integrations.retry.circuit_open_seconds', 60),
        );
    }

    private function recordFailure(string $integrationKey, string $operationName, string $message, IntegrationRetryPolicy $policy): void
    {
        $row = $this->db->table(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS)
            ->where('integration_key', $integrationKey)
            ->where('operation', $operationName)
            ->first();
        $failures = is_object($row) && is_numeric($row->failure_count ?? null) ? (int) $row->failure_count + 1 : 1;
        $state = $failures >= $policy->circuitFailureThreshold ? IntegrationCircuitState::Open : IntegrationCircuitState::Closed;

        $this->db->table(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS)->upsert([
            [
                'integration_key' => $integrationKey,
                'operation' => $operationName,
                'state' => $state->value,
                'failure_count' => $failures,
                'opened_until' => $state === IntegrationCircuitState::Open ? now()->addSeconds($policy->circuitOpenSeconds) : null,
                'last_failure_at' => now(),
                'last_error_message' => mb_substr($message, 0, 1000),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['integration_key', 'operation'], ['state', 'failure_count', 'opened_until', 'last_failure_at', 'last_error_message', 'updated_at']);

        $this->db->table(DatabaseTable::INTEGRATION_CONNECTIONS)->where('integration_key', $integrationKey)->update([
            'last_error_at' => now(),
            'last_error_message' => mb_substr($message, 0, 1000),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    private function audit(string $integrationKey, string $action, string $result, ?int $teamId, array $context = []): void
    {
        $teamPublicId = $teamId === null ? null : $this->publicId(DatabaseTable::TEAMS, $teamId);

        $this->audit->record(new AuditEvent(
            module: 'integrations',
            action: $action,
            result: $result,
            source: app()->runningInConsole() ? 'system' : 'http',
            targetType: 'integration',
            targetPublicId: $integrationKey,
            aggregateType: 'integration',
            aggregatePublicId: $integrationKey,
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
}
