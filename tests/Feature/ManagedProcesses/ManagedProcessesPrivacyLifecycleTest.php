<?php

declare(strict_types=1);

namespace Tests\Feature\ManagedProcesses;

use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Application\Lifecycle\ManagedProcessDataLifecycleParticipant;
use App\Modules\Optional\ManagedProcesses\Application\Public\Persistence\ManagedProcessesDatabaseTable;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ManagedProcessesPrivacyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_preview_reports_process_copies_and_blocks_active_runs(): void
    {
        $subject = new DataLifecycleSubject('person', '01JMANAGEDPRIVACY00000001');
        $this->insertRun($subject->identifier, ProcessRunStatus::Running);
        $this->insertLog($subject->identifier);
        $this->insertSchedule($subject->identifier);
        $this->insertQueuedJob($subject->identifier);

        $preview = $this->app->make(ManagedProcessDataLifecycleParticipant::class)
            ->preview($subject, DataLifecycleOperation::Delete);

        $dataSets = collect($preview->impacts)->map->dataSet->all();

        self::assertContains('managed_processes.process_runs', $dataSets);
        self::assertContains('managed_processes.process_logs', $dataSets);
        self::assertContains('managed_processes.process_schedules', $dataSets);
        self::assertContains('managed_processes.queued_work', $dataSets);
        self::assertSame('managed_process_active_run', $preview->blockers[0]->code);
    }

    public function test_privacy_execution_redacts_completed_process_copies_and_removes_queued_work(): void
    {
        $subject = new DataLifecycleSubject('person', '01JMANAGEDPRIVACY00000002');
        $runPublicId = $this->insertRun($subject->identifier, ProcessRunStatus::Succeeded);
        $logPublicId = $this->insertLog($subject->identifier);
        $schedulePublicId = $this->insertSchedule($subject->identifier);
        $this->insertQueuedJob($subject->identifier);

        $result = $this->app->make(ManagedProcessDataLifecycleParticipant::class)
            ->execute($subject, DataLifecycleOperation::Anonymize, (string) Str::uuid());

        self::assertTrue($result->completed());
        self::assertSame(
            ['managed_processes.runs_redacted', 'managed_processes.logs_redacted', 'managed_processes.schedules_redacted', 'managed_processes.queued_work_removed'],
            collect($result->steps)->map->step->all(),
        );
        $this->assertDatabaseHas(ManagedProcessesDatabaseTable::RUNS, [
            'public_id' => $runPublicId,
            'input_snapshot' => '{"privacy":"redacted"}',
            'result_summary' => null,
            'safe_error_summary' => null,
            'cancel_reason' => null,
        ]);
        $this->assertDatabaseHas(ManagedProcessesDatabaseTable::LOG_EVENTS, [
            'public_id' => $logPublicId,
            'message' => 'Privacy-controlled process log content redacted.',
            'safe_context' => null,
            'entity_public_id' => null,
            'external_reference' => null,
            'source_reference' => null,
        ]);
        $this->assertDatabaseHas(ManagedProcessesDatabaseTable::SCHEDULES, [
            'public_id' => $schedulePublicId,
            'input_snapshot' => '{"privacy":"redacted"}',
            'reason' => 'Privacy-controlled schedule content redacted.',
        ]);
        $this->assertDatabaseMissing(DatabaseTable::JOBS, [
            'payload' => json_encode(['subject' => $subject->identifier], JSON_THROW_ON_ERROR),
        ]);
    }

    private function insertRun(string $subjectIdentifier, ProcessRunStatus $status): string
    {
        $publicId = (string) Str::ulid();

        DB::table(ManagedProcessesDatabaseTable::RUNS)->insert([
            'public_id' => $publicId,
            'process_key' => 'privacy.test',
            'module_key' => 'managed_processes',
            'scope' => 'global',
            'team_id' => null,
            'actor_user_id' => null,
            'source_type' => 'maintenance',
            'input_snapshot' => json_encode(['subject' => $subjectIdentifier], JSON_THROW_ON_ERROR),
            'queue_connection' => 'redis',
            'queue_name' => 'managed-processes',
            'job_identifier' => null,
            'status' => $status->value,
            'current_stage' => 'finished',
            'progress_current' => 1,
            'progress_total' => 1,
            'progress_label' => 'Finished',
            'counters' => json_encode(['processed' => 1], JSON_THROW_ON_ERROR),
            'correlation_id' => (string) Str::uuid(),
            'causation_id' => null,
            'queued_at' => now(),
            'started_at' => now(),
            'finished_at' => $status->terminal() ? now() : null,
            'failed_at' => null,
            'cancelled_at' => null,
            'retried_at' => null,
            'result_summary' => json_encode(['subject' => $subjectIdentifier], JSON_THROW_ON_ERROR),
            'safe_error_summary' => 'No error for '.$subjectIdentifier,
            'cancel_reason' => 'Cancel reason for '.$subjectIdentifier,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }

    private function insertLog(string $subjectIdentifier): string
    {
        $runId = DB::table(ManagedProcessesDatabaseTable::RUNS)->orderByDesc('id')->value('id');
        $publicId = (string) Str::ulid();

        DB::table(ManagedProcessesDatabaseTable::LOG_EVENTS)->insert([
            'public_id' => $publicId,
            'process_run_id' => $runId,
            'occurred_at' => now(),
            'severity' => 'info',
            'event_type' => 'entity',
            'stage' => 'map',
            'message' => 'Mapped subject '.$subjectIdentifier,
            'safe_context' => json_encode(['subject' => $subjectIdentifier], JSON_THROW_ON_ERROR),
            'row_number' => null,
            'entity_public_id' => $subjectIdentifier,
            'external_reference' => 'external-'.$subjectIdentifier,
            'source_reference' => 'source-'.$subjectIdentifier,
            'error_code' => null,
            'exception_class' => null,
            'retryable' => null,
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }

    private function insertSchedule(string $subjectIdentifier): string
    {
        $publicId = (string) Str::ulid();

        DB::table(ManagedProcessesDatabaseTable::SCHEDULES)->insert([
            'public_id' => $publicId,
            'process_key' => 'privacy.test',
            'module_key' => 'managed_processes',
            'scope' => 'global',
            'team_id' => null,
            'timezone' => 'Europe/Warsaw',
            'cron_expression' => '0 2 * * *',
            'interval_key' => null,
            'input_snapshot' => json_encode(['subject' => $subjectIdentifier], JSON_THROW_ON_ERROR),
            'enabled' => true,
            'next_due_at' => now()->addDay(),
            'last_run_id' => null,
            'overlap_policy' => 'skip',
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'reason' => 'Schedule references '.$subjectIdentifier,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }

    private function insertQueuedJob(string $subjectIdentifier): void
    {
        DB::table(DatabaseTable::JOBS)->insert([
            'queue' => 'managed-processes',
            'payload' => json_encode(['subject' => $subjectIdentifier], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->unix(),
            'created_at' => now()->unix(),
        ]);
    }
}
