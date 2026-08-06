<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Lifecycle;

use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Application\Public\Persistence\ManagedProcessesDatabaseTable;
use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use App\Shared\Application\DataLifecycle\DataLifecycleBlocker;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class ManagedProcessDataLifecycleParticipant implements DataLifecycleParticipant
{
    public function __construct(private ConnectionInterface $db) {}

    public function preview(DataLifecycleSubject $subject, DataLifecycleOperation $operation): DataLifecyclePreview
    {
        $impacts = array_values(array_filter([
            $this->impact('managed_processes.process_runs', $this->matchingRuns($subject)->count(), $this->records($this->matchingRuns($subject), [
                'id',
                'public_id',
                'process_key',
                'module_key',
                'scope',
                'team_id',
                'actor_user_id',
                'source_type',
                'queue_connection',
                'queue_name',
                'job_identifier',
                'status',
                'current_stage',
                'correlation_id',
                'causation_id',
                'retry_of_run_id',
                'queued_at',
                'started_at',
                'finished_at',
                'failed_at',
                'cancelled_at',
                'created_at',
            ])),
            $this->impact('managed_processes.process_logs', $this->matchingLogs($subject)->count(), $this->records($this->matchingLogs($subject), [
                'id',
                'public_id',
                'process_run_id',
                'occurred_at',
                'severity',
                'event_type',
                'stage',
                'entity_public_id',
                'external_reference',
                'source_reference',
                'error_code',
                'exception_class',
                'retryable',
                'correlation_id',
                'created_at',
            ])),
            $this->impact('managed_processes.process_schedules', $this->matchingSchedules($subject)->count(), $this->records($this->matchingSchedules($subject), [
                'id',
                'public_id',
                'process_key',
                'module_key',
                'scope',
                'team_id',
                'timezone',
                'cron_expression',
                'interval_key',
                'enabled',
                'next_due_at',
                'last_run_id',
                'overlap_policy',
                'created_by_user_id',
                'updated_by_user_id',
                'created_at',
            ])),
            $this->impact('managed_processes.queued_work', $this->matchingQueuedJobs($subject)->count(), $this->records($this->matchingQueuedJobs($subject), [
                'id',
                'queue',
                'attempts',
                'reserved_at',
                'available_at',
                'created_at',
            ])),
        ]));
        $activeRuns = $this->matchingRuns($subject)
            ->whereIn('status', array_map(static fn (ProcessRunStatus $status): string => $status->value, $this->activeStatuses()))
            ->count();
        $blockers = [];

        if ($activeRuns > 0) {
            $blockers[] = new DataLifecycleBlocker(
                code: 'managed_process_active_run',
                message: 'Subject is referenced by an active managed process run.',
            );
        }

        return new DataLifecyclePreview($impacts, $blockers);
    }

    public function execute(DataLifecycleSubject $subject, DataLifecycleOperation $operation, string $correlationId): DataLifecycleResult
    {
        $activeRuns = $this->matchingRuns($subject)
            ->whereIn('status', array_map(static fn (ProcessRunStatus $status): string => $status->value, $this->activeStatuses()))
            ->count();

        if ($activeRuns > 0) {
            return new DataLifecycleResult([], [
                new DataLifecycleBlocker(
                    code: 'managed_process_active_run',
                    message: 'Subject is referenced by an active managed process run.',
                ),
            ]);
        }

        return new DataLifecycleResult([
            new DataLifecycleStepResult('managed_processes.runs_redacted', $this->redactRuns($subject), true),
            new DataLifecycleStepResult('managed_processes.logs_redacted', $this->redactLogs($subject), true),
            new DataLifecycleStepResult('managed_processes.schedules_redacted', $this->redactSchedules($subject), true),
            new DataLifecycleStepResult('managed_processes.queued_work_removed', $this->removeQueuedJobs($subject), true),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     */
    private function impact(string $dataSet, int $records, array $details): ?DataLifecycleImpact
    {
        return $records > 0 ? new DataLifecycleImpact($dataSet, $records, true, $details) : null;
    }

    private function matchingRuns(DataLifecycleSubject $subject): Builder
    {
        return $this->db->table(ManagedProcessesDatabaseTable::RUNS)
            ->where(function (Builder $query) use ($subject): void {
                $query
                    ->where('public_id', $subject->identifier)
                    ->orWhere('correlation_id', $subject->identifier)
                    ->orWhere('causation_id', $subject->identifier);

                $this->orJsonTextContains($query, 'input_snapshot', $subject->identifier);
                $this->orJsonTextContains($query, 'result_summary', $subject->identifier);
                $this->orTextContains($query, 'safe_error_summary', $subject->identifier);
                $this->orTextContains($query, 'cancel_reason', $subject->identifier);
            });
    }

    private function matchingLogs(DataLifecycleSubject $subject): Builder
    {
        return $this->db->table(ManagedProcessesDatabaseTable::LOG_EVENTS)
            ->where(function (Builder $query) use ($subject): void {
                $query
                    ->where('correlation_id', $subject->identifier)
                    ->orWhere('entity_public_id', $subject->identifier)
                    ->orWhere('external_reference', $subject->identifier)
                    ->orWhere('source_reference', $subject->identifier);

                $this->orTextContains($query, 'message', $subject->identifier);
                $this->orJsonTextContains($query, 'safe_context', $subject->identifier);
            });
    }

    private function matchingSchedules(DataLifecycleSubject $subject): Builder
    {
        return $this->db->table(ManagedProcessesDatabaseTable::SCHEDULES)
            ->where(function (Builder $query) use ($subject): void {
                $this->orJsonTextContains($query, 'input_snapshot', $subject->identifier);
                $this->orTextContains($query, 'reason', $subject->identifier);
            });
    }

    private function matchingQueuedJobs(DataLifecycleSubject $subject): Builder
    {
        return $this->db->table(DatabaseTable::JOBS)
            ->where(function (Builder $query) use ($subject): void {
                $this->orTextContains($query, 'payload', $subject->identifier);
            });
    }

    private function redactRuns(DataLifecycleSubject $subject): int
    {
        return $this->matchingRuns($subject)->update([
            'input_snapshot' => $this->redactedPayload(),
            'result_summary' => null,
            'safe_error_summary' => null,
            'cancel_reason' => null,
            'updated_at' => now(),
        ]);
    }

    private function redactLogs(DataLifecycleSubject $subject): int
    {
        return $this->matchingLogs($subject)->update([
            'message' => 'Privacy-controlled process log content redacted.',
            'safe_context' => null,
            'entity_public_id' => null,
            'external_reference' => null,
            'source_reference' => null,
            'updated_at' => now(),
        ]);
    }

    private function redactSchedules(DataLifecycleSubject $subject): int
    {
        return $this->matchingSchedules($subject)->update([
            'input_snapshot' => $this->redactedPayload(),
            'reason' => 'Privacy-controlled schedule content redacted.',
            'updated_at' => now(),
        ]);
    }

    private function removeQueuedJobs(DataLifecycleSubject $subject): int
    {
        return $this->matchingQueuedJobs($subject)->delete();
    }

    /**
     * @return list<ProcessRunStatus>
     */
    private function activeStatuses(): array
    {
        return [
            ProcessRunStatus::Draft,
            ProcessRunStatus::Queued,
            ProcessRunStatus::Running,
            ProcessRunStatus::Waiting,
        ];
    }

    private function orTextContains(Builder $query, string $column, string $value): void
    {
        match ($column) {
            'cancel_reason' => $query->orWhereRaw('cancel_reason LIKE ?', [$this->like($value)]),
            'idempotency_key' => $query->orWhereRaw('idempotency_key LIKE ?', [$this->like($value)]),
            'logs.context_json' => $query->orWhereRaw('logs.context_json LIKE ?', [$this->like($value)]),
            'logs.message' => $query->orWhereRaw('logs.message LIKE ?', [$this->like($value)]),
            'message' => $query->orWhereRaw('message LIKE ?', [$this->like($value)]),
            'payload' => $query->orWhereRaw('payload LIKE ?', [$this->like($value)]),
            'reason' => $query->orWhereRaw('reason LIKE ?', [$this->like($value)]),
            'runs.idempotency_key' => $query->orWhereRaw('runs.idempotency_key LIKE ?', [$this->like($value)]),
            'runs.safe_error_summary' => $query->orWhereRaw('runs.safe_error_summary LIKE ?', [$this->like($value)]),
            'safe_error_summary' => $query->orWhereRaw('safe_error_summary LIKE ?', [$this->like($value)]),
            default => null,
        };
    }

    private function orJsonTextContains(Builder $query, string $column, string $value): void
    {
        match ($column) {
            'input_payload' => $query->orWhereRaw('input_payload::text LIKE ?', [$this->like($value)]),
            'input_snapshot' => $query->orWhereRaw('input_snapshot::text LIKE ?', [$this->like($value)]),
            'metadata' => $query->orWhereRaw('metadata::text LIKE ?', [$this->like($value)]),
            'progress_payload' => $query->orWhereRaw('progress_payload::text LIKE ?', [$this->like($value)]),
            'result_summary' => $query->orWhereRaw('result_summary::text LIKE ?', [$this->like($value)]),
            'runs.input_payload' => $query->orWhereRaw('runs.input_payload::text LIKE ?', [$this->like($value)]),
            'runs.metadata' => $query->orWhereRaw('runs.metadata::text LIKE ?', [$this->like($value)]),
            'safe_context' => $query->orWhereRaw('safe_context::text LIKE ?', [$this->like($value)]),
            default => null,
        };
    }

    private function like(string $value): string
    {
        return '%'.$value.'%';
    }

    private function redactedPayload(): string
    {
        return json_encode(['privacy' => 'redacted'], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function records(Builder $query, array $columns): array
    {
        $records = [];

        foreach ($query->get($columns) as $record) {
            $records[] = $this->recordToArray($record);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordToArray(object $record): array
    {
        $row = [];

        foreach ((array) $record as $key => $value) {
            if (is_string($key)) {
                $row[$key] = $value;
            }
        }

        return $row;
    }
}
