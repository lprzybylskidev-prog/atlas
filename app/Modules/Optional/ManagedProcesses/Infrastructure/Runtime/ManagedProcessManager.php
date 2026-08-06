<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimePublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\DTOs\ProcessLogEntry;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessLogSeverity;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Application\Permissions\ManagedProcessesPermissionCatalog;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\ProcessDefinition;
use App\Modules\Optional\ManagedProcesses\Application\Public\Persistence\ManagedProcessesDatabaseTable;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessRequest;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

final readonly class ManagedProcessManager implements ManagedProcessRunner
{
    public function __construct(
        private ProcessDefinitionRegistry $definitions,
        private ModuleGate $moduleGate,
        private ConnectionInterface $database,
        private AuditRecorder $audit,
        private NotificationPublisher $notifications,
        private RealtimePublisher $realtime,
    ) {}

    public function start(
        string $processKey,
        string $sourceType,
        ?array $input,
        ?string $actorPublicId,
        ?string $teamPublicId,
        ?string $causationId = null,
    ): string {
        $definition = $this->definition($processKey);
        $this->authorize($definition, $definition->permissions->run, $actorPublicId, $teamPublicId);

        if (! $definition->manualStartSupported && $sourceType === 'manual') {
            throw new RuntimeException('This process does not support manual starts.');
        }

        $teamId = $this->teamId($teamPublicId);
        $actorId = $this->userId($actorPublicId);
        $this->assertConcurrencyAvailable($definition, $teamId, $actorId);

        $publicId = (string) Str::ulid();
        $correlationId = $this->correlationId();
        $now = now();

        $this->database->table(ManagedProcessesDatabaseTable::RUNS)->insert([
            'public_id' => $publicId,
            'process_key' => $definition->key,
            'module_key' => $definition->moduleKey,
            'scope' => $definition->scope,
            'team_id' => $teamId,
            'actor_user_id' => $actorId,
            'source_type' => $sourceType,
            'input_snapshot' => $this->json($input ?? ['_input' => 'none']),
            'queue_connection' => config()->string('queue.default'),
            'queue_name' => $definition->queueName,
            'job_identifier' => null,
            'status' => ProcessRunStatus::Queued->value,
            'current_stage' => 'queued',
            'progress_current' => 0,
            'progress_total' => null,
            'progress_label' => 'Queued',
            'counters' => $this->json($this->emptyCounters()),
            'correlation_id' => $correlationId,
            'causation_id' => $causationId,
            'queued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->persistDefinition($definition);
        $this->log($publicId, new ProcessLogEntry(ProcessLogSeverity::Info, 'message', 'Process run queued.', 'queued'));
        $this->recordAudit('managed_process.run_created', 'succeeded', $publicId, $actorPublicId, $teamPublicId, ['process_key' => $definition->key, 'source_type' => $sourceType]);
        $this->execute($definition, $publicId);

        return $publicId;
    }

    public function retry(string $runPublicId, ?string $actorPublicId, ?string $teamPublicId, string $reason): string
    {
        $run = $this->run($runPublicId);
        $definition = $this->definition($this->requiredString($run, 'process_key'));
        $this->authorize($definition, $definition->permissions->retry, $actorPublicId, $teamPublicId);

        if (! $definition->retryPolicy->retryable || ! in_array($this->requiredString($run, 'status'), [ProcessRunStatus::Failed->value, ProcessRunStatus::SucceededWithWarnings->value, ProcessRunStatus::Cancelled->value], true)) {
            throw new RuntimeException('This run cannot be retried.');
        }

        $input = $this->decodeJson($run->input_snapshot);
        $newRun = $this->start($definition->key, 'retry', $input, $actorPublicId, $teamPublicId, $this->requiredString($run, 'correlation_id'));
        $newRunId = $this->runId($newRun);

        $this->database->table(ManagedProcessesDatabaseTable::RUNS)
            ->where('public_id', $newRun)
            ->update([
                'retry_of_run_id' => $this->intValue($run->id),
                'updated_at' => now(),
            ]);
        $this->database->table(ManagedProcessesDatabaseTable::RUNS)
            ->where('public_id', $runPublicId)
            ->update([
                'retried_at' => now(),
                'updated_at' => now(),
            ]);

        $this->log($runPublicId, new ProcessLogEntry(ProcessLogSeverity::Info, 'checkpoint', 'Retry requested.', 'retry', ['reason' => $reason, 'new_run_public_id' => $newRun]));
        $this->recordAudit('managed_process.run_retried', 'succeeded', $runPublicId, $actorPublicId, $teamPublicId, ['new_run_id' => $newRunId, 'reason' => $reason]);

        return $newRun;
    }

    public function cancel(string $runPublicId, ?string $actorPublicId, ?string $teamPublicId, string $reason): void
    {
        $run = $this->run($runPublicId);
        $definition = $this->definition($this->requiredString($run, 'process_key'));
        $this->authorize($definition, $definition->permissions->cancel, $actorPublicId, $teamPublicId);

        if ($definition->cancellationPolicy === 'none' || ! ProcessRunStatus::from($this->requiredString($run, 'status'))->active()) {
            throw new RuntimeException('This run cannot be cancelled.');
        }

        $this->database->table(ManagedProcessesDatabaseTable::RUNS)
            ->where('public_id', $runPublicId)
            ->update([
                'status' => ProcessRunStatus::Cancelled->value,
                'cancelled_at' => now(),
                'finished_at' => now(),
                'cancel_reason' => $reason,
                'progress_label' => 'Cancelled',
                'updated_at' => now(),
            ]);

        $this->log($runPublicId, new ProcessLogEntry(ProcessLogSeverity::Warning, 'checkpoint', 'Process run cancelled.', 'cancelled', ['reason' => $reason]));
        $this->recordAudit('managed_process.run_cancelled', 'succeeded', $runPublicId, $actorPublicId, $teamPublicId, ['reason' => $reason]);
    }

    public function log(string $runPublicId, ProcessLogEntry $entry): void
    {
        if ($entry->severity === ProcessLogSeverity::Debug && ! config()->boolean('atlas.managed_processes.debug_logs', false)) {
            return;
        }

        $run = $this->run($runPublicId);

        $this->database->table(ManagedProcessesDatabaseTable::LOG_EVENTS)->insert([
            'public_id' => (string) Str::ulid(),
            'process_run_id' => $this->intValue($run->id),
            'occurred_at' => now(),
            'severity' => $entry->severity->value,
            'event_type' => $entry->eventType,
            'stage' => $entry->stage,
            'message' => $entry->message,
            'safe_context' => $entry->safeContext === null ? null : $this->json($this->safeContext($entry->safeContext)),
            'row_number' => $entry->rowNumber,
            'entity_public_id' => $entry->entityPublicId,
            'external_reference' => $entry->externalReference,
            'source_reference' => $entry->sourceReference,
            'error_code' => $entry->errorCode,
            'exception_class' => $entry->exceptionClass,
            'retryable' => $entry->retryable,
            'correlation_id' => $this->requiredString($run, 'correlation_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateProgress(
        string $runPublicId,
        ProcessRunStatus $status,
        ?string $stage = null,
        ?int $current = null,
        ?int $total = null,
        ?string $label = null,
        ?array $counters = null,
        ?array $resultSummary = null,
        ?string $safeErrorSummary = null,
    ): void {
        $updates = [
            'status' => $status->value,
            'updated_at' => now(),
        ];

        if ($stage !== null) {
            $updates['current_stage'] = $stage;
        }
        if ($current !== null) {
            $updates['progress_current'] = $current;
        }
        if ($total !== null) {
            $updates['progress_total'] = $total;
        }
        if ($label !== null) {
            $updates['progress_label'] = $label;
        }
        if ($counters !== null) {
            $updates['counters'] = $this->json(array_merge($this->emptyCounters(), $counters));
        }
        if ($resultSummary !== null) {
            $updates['result_summary'] = $this->json($this->safeContext($resultSummary));
        }
        if ($safeErrorSummary !== null) {
            $updates['safe_error_summary'] = mb_substr($safeErrorSummary, 0, 2000);
        }

        if ($status->terminal()) {
            $updates['finished_at'] = now();
        }
        if ($status === ProcessRunStatus::Failed) {
            $updates['failed_at'] = now();
        }
        if ($status === ProcessRunStatus::Cancelled) {
            $updates['cancelled_at'] = now();
        }

        $this->database->table(ManagedProcessesDatabaseTable::RUNS)->where('public_id', $runPublicId)->update($updates);

        if ($status === ProcessRunStatus::Running) {
            $this->database->table(ManagedProcessesDatabaseTable::RUNS)
                ->where('public_id', $runPublicId)
                ->whereNull('started_at')
                ->update([
                    'started_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $this->publishRealtimeProgress($runPublicId, $status, $current, $total, $label);

        if ($status->terminal()) {
            $this->publishTerminalNotification($runPublicId, $status);
        }
    }

    private function execute(ProcessDefinition $definition, string $runPublicId): void
    {
        if ($definition->executionMode !== 'sync') {
            dispatch(new ExecuteManagedProcessJob($runPublicId))->onQueue($definition->queueName);

            return;
        }

        app(ExecuteManagedProcessJob::class, ['runPublicId' => $runPublicId])->handle($this->definitions);
    }

    private function assertConcurrencyAvailable(ProcessDefinition $definition, ?int $teamId, ?int $actorId): void
    {
        $query = $this->database->table(ManagedProcessesDatabaseTable::RUNS)
            ->where('process_key', $definition->key)
            ->whereIn('status', [ProcessRunStatus::Draft->value, ProcessRunStatus::Queued->value, ProcessRunStatus::Running->value, ProcessRunStatus::Waiting->value]);

        if ($definition->concurrencyPolicy === 'one_active_per_team') {
            $query->where('team_id', $teamId);
        } elseif ($definition->concurrencyPolicy === 'one_active_per_actor') {
            $query->where('actor_user_id', $actorId);
        }

        if ((int) $query->count() >= $definition->parallelism) {
            throw new RuntimeException('Process concurrency limit is exhausted; retry after the active run finishes.');
        }
    }

    private function authorize(ProcessDefinition $definition, string $permission, ?string $actorPublicId, ?string $teamPublicId): void
    {
        if (! $this->moduleGate->allows(new ModuleAccessRequest(
            moduleKey: $definition->moduleKey,
            activeTeamPublicId: $teamPublicId,
            userPublicId: $actorPublicId,
            requiredPermission: $permission,
        ))) {
            throw new RuntimeException('The process is not available for the current actor, team, module, or permission context.');
        }
    }

    private function persistDefinition(ProcessDefinition $definition): void
    {
        $this->database->table(ManagedProcessesDatabaseTable::DEFINITIONS)->updateOrInsert(
            ['process_key' => $definition->key],
            [
                'public_id' => (string) Str::ulid(),
                'module_key' => $definition->moduleKey,
                'label' => $definition->label,
                'description' => $definition->description,
                'scope' => $definition->scope,
                'input_schema' => $definition->inputSchema === null ? null : $this->json($definition->inputSchema),
                'permissions' => $this->json($definition->permissions->toArray()),
                'queue_name' => $definition->queueName,
                'execution_mode' => $definition->executionMode,
                'concurrency_policy' => $definition->concurrencyPolicy,
                'parallelism' => $definition->parallelism,
                'retry_policy' => $this->json($definition->retryPolicy->toArray()),
                'cancellation_policy' => $definition->cancellationPolicy,
                'schedule_supported' => $definition->scheduleSupported,
                'manual_start_supported' => $definition->manualStartSupported,
                'external_effects' => $definition->externalEffects,
                'high_risk' => $definition->highRisk,
                'blocks_module_deactivation' => $definition->blocksModuleDeactivation,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function publishTerminalNotification(string $runPublicId, ProcessRunStatus $status): void
    {
        $run = $this->run($runPublicId);
        $actorPublicId = $this->userPublicId($this->intValue($run->actor_user_id));

        if ($actorPublicId === null) {
            return;
        }

        $titleKey = match ($status) {
            ProcessRunStatus::Succeeded => 'notifications.managed_process.succeeded.title',
            ProcessRunStatus::SucceededWithWarnings => 'notifications.managed_process.warning.title',
            ProcessRunStatus::Failed, ProcessRunStatus::Cancelled, ProcessRunStatus::Expired => 'notifications.managed_process.failed.title',
            default => 'notifications.managed_process.finished.title',
        };
        $bodyKey = match ($status) {
            ProcessRunStatus::Succeeded => 'notifications.managed_process.succeeded.body',
            ProcessRunStatus::SucceededWithWarnings => 'notifications.managed_process.warning.body',
            ProcessRunStatus::Failed, ProcessRunStatus::Cancelled, ProcessRunStatus::Expired => 'notifications.managed_process.failed.body',
            default => 'notifications.managed_process.finished.body',
        };

        $this->notifications->publish(new CreateNotification(
            type: match ($status) {
                ProcessRunStatus::Succeeded => 'managed_process.succeeded',
                ProcessRunStatus::SucceededWithWarnings => 'managed_process.warning',
                ProcessRunStatus::Failed, ProcessRunStatus::Cancelled, ProcessRunStatus::Expired => 'managed_process.failed',
                default => 'managed_process.finished',
            },
            title: $titleKey,
            body: $bodyKey,
            recipientUserPublicId: $actorPublicId,
            teamPublicId: $this->teamPublicId($this->intValue($run->team_id)),
            severity: in_array($status, [ProcessRunStatus::Failed, ProcessRunStatus::Cancelled], true) ? 'warning' : 'success',
            deepLinkUrl: $this->terminalNotificationDeepLinkUrl($runPublicId, $actorPublicId, $this->teamPublicId($this->intValue($run->team_id))),
            data: [
                'run_public_id' => $runPublicId,
                'process_name' => $this->processLabel($this->requiredString($run, 'process_key')),
                'title_key' => $titleKey,
                'body_key' => $bodyKey,
            ],
        ));
    }

    private function terminalNotificationDeepLinkUrl(string $runPublicId, string $actorPublicId, ?string $teamPublicId): ?string
    {
        if ($this->moduleGate->allows(new ModuleAccessRequest(
            moduleKey: 'managed_processes',
            activeTeamPublicId: $teamPublicId,
            userPublicId: $actorPublicId,
            requiredPermission: ManagedProcessesPermissionCatalog::SHOW,
        ))) {
            return '/admin/managed-processes/'.$runPublicId;
        }

        return null;
    }

    private function processLabel(string $processKey): string
    {
        $label = $this->database->table(ManagedProcessesDatabaseTable::DEFINITIONS)
            ->where('process_key', $processKey)
            ->value('label');

        return is_string($label) && $label !== '' ? $label : 'Process';
    }

    private function publishRealtimeProgress(string $runPublicId, ProcessRunStatus $status, ?int $current, ?int $total, ?string $label): void
    {
        $run = $this->run($runPublicId);
        $percent = 0;

        if ($total !== null && $total > 0 && $current !== null) {
            $percent = max(0, min(100, (int) floor(($current / $total) * 100)));
        }

        $this->realtime->publishOperationProgress(
            operationType: 'managed_process',
            operationId: $runPublicId,
            status: $status->value,
            progressPercent: $percent,
            userPublicId: $this->userPublicId($this->intValue($run->actor_user_id)),
            teamPublicId: $this->teamPublicId($this->intValue($run->team_id)),
            message: $label,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordAudit(string $action, string $result, string $targetPublicId, ?string $actorPublicId, ?string $teamPublicId, array $metadata): void
    {
        $this->audit->record(new AuditEvent(
            action: $action,
            result: $result,
            module: 'managed_processes',
            source: 'admin',
            actorPublicId: $actorPublicId,
            targetType: 'managed_process_run',
            targetPublicId: $targetPublicId,
            teamPublicId: $teamPublicId,
            metadata: $metadata,
        ));
    }

    private function definition(string $processKey): ProcessDefinition
    {
        return $this->definitions->get($processKey) ?? throw new RuntimeException('Managed process definition is not registered.');
    }

    private function run(string $publicId): stdClass
    {
        $run = $this->database->table(ManagedProcessesDatabaseTable::RUNS)->where('public_id', $publicId)->first();

        if ($run instanceof stdClass) {
            return $run;
        }

        throw new RuntimeException('Managed process run was not found.');
    }

    private function runId(string $publicId): int
    {
        $id = $this->database->table(ManagedProcessesDatabaseTable::RUNS)->where('public_id', $publicId)->value('id');

        return $this->intValue($id);
    }

    private function userId(?string $publicId): ?int
    {
        if ($publicId === null) {
            return null;
        }

        $id = $this->database->table(IdentityDatabaseTable::USERS)->where('public_id', $publicId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function userPublicId(?int $id): ?string
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        $publicId = $this->database->table(IdentityDatabaseTable::USERS)->where('id', $id)->value('public_id');

        return is_string($publicId) ? $publicId : null;
    }

    private function teamId(?string $publicId): ?int
    {
        if ($publicId === null) {
            return null;
        }

        $id = $this->database->table(TeamsDatabaseTable::TEAMS)->where('public_id', $publicId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function teamPublicId(?int $id): ?string
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        $publicId = $this->database->table(TeamsDatabaseTable::TEAMS)->where('id', $id)->value('public_id');

        return is_string($publicId) ? $publicId : null;
    }

    private function correlationId(): string
    {
        $context = Context::get('correlation_id');

        return is_string($context) && $context !== '' ? $context : (string) Str::uuid();
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounters(): array
    {
        return ['processed' => 0, 'success' => 0, 'info' => 0, 'warning' => 0, 'error' => 0, 'failed' => 0, 'skipped' => 0, 'retried' => 0];
    }

    /**
     * @param  array<string, scalar|null>  $context
     * @return array<string, scalar|null>
     */
    private function safeContext(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            if (preg_match('/secret|token|password|credential/i', $key) === 1) {
                $safe[$key] = '[redacted]';

                continue;
            }

            $safe[$key] = is_string($value) ? mb_substr($value, 0, 500) : $value;
        }

        return $safe;
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        $normalized = [];

        foreach ($decoded as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function requiredString(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new RuntimeException(sprintf('Expected [%s] to be a scalar value.', $property));
    }
}
