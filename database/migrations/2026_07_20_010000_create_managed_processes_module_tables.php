<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\DatabaseSchema;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::OPTIONAL_MANAGED_PROCESSES);

        $this->releaseLegacyReportProcessDependency();

        Schema::dropIfExists(DatabaseTable::REPORT_RENDER_CREDENTIALS);
        Schema::dropIfExists(DatabaseTable::REPORT_EXPORT_ARTIFACTS);
        Schema::dropIfExists(DatabaseTable::REPORT_EXPORT_REQUESTS);
        Schema::dropIfExists(DatabaseTable::IMPORT_IDEMPOTENCY_KEYS);
        Schema::dropIfExists(DatabaseTable::IMPORT_ROW_ERRORS);
        Schema::dropIfExists(DatabaseTable::IMPORT_EXECUTIONS);
        $this->dropModuleTables();

        Schema::create(DatabaseTable::MANAGED_PROCESS_DEFINITIONS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('process_key')->unique();
            $table->string('module_key');
            $table->string('label');
            $table->text('description');
            $table->string('scope', 32);
            $table->jsonb('input_schema')->nullable();
            $table->jsonb('permissions');
            $table->string('queue_name');
            $table->string('execution_mode', 32);
            $table->string('concurrency_policy', 64);
            $table->unsignedInteger('parallelism')->default(1);
            $table->jsonb('retry_policy');
            $table->string('cancellation_policy', 32);
            $table->boolean('schedule_supported')->default(false);
            $table->boolean('manual_start_supported')->default(true);
            $table->boolean('external_effects')->default(false);
            $table->boolean('high_risk')->default(false);
            $table->boolean('blocks_module_deactivation')->default(true);
            $table->timestampsTz();

            $table->index(['module_key', 'process_key']);
            $table->index(['schedule_supported', 'manual_start_supported']);
        });

        Schema::create(DatabaseTable::MANAGED_PROCESS_RUNS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('process_key');
            $table->string('module_key');
            $table->string('scope', 32);
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained(DatabaseTable::USERS)->nullOnDelete();
            $table->string('source_type', 32);
            $table->jsonb('input_snapshot')->nullable();
            $table->string('queue_connection')->nullable();
            $table->string('queue_name')->nullable();
            $table->string('job_identifier')->nullable();
            $table->string('status', 32);
            $table->string('current_stage')->nullable();
            $table->unsignedBigInteger('progress_current')->default(0);
            $table->unsignedBigInteger('progress_total')->nullable();
            $table->string('progress_label')->nullable();
            $table->jsonb('counters');
            $table->string('correlation_id');
            $table->string('causation_id')->nullable();
            $table->foreignId('retry_of_run_id')->nullable()->constrained(DatabaseTable::MANAGED_PROCESS_RUNS)->restrictOnDelete();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('retried_at')->nullable();
            $table->jsonb('result_summary')->nullable();
            $table->text('safe_error_summary')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestampsTz();

            $table->index(['process_key', 'status']);
            $table->index(['module_key', 'status']);
            $table->index(['team_id', 'status']);
            $table->index(['actor_user_id', 'status']);
            $table->index(['source_type', 'created_at']);
            $table->index(['correlation_id']);
            $table->index(['retry_of_run_id']);
        });

        Schema::create(DatabaseTable::MANAGED_PROCESS_LOG_EVENTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('process_run_id')->constrained(DatabaseTable::MANAGED_PROCESS_RUNS)->restrictOnDelete();
            $table->timestampTz('occurred_at');
            $table->string('severity', 16);
            $table->string('event_type', 32);
            $table->string('stage')->nullable();
            $table->text('message');
            $table->jsonb('safe_context')->nullable();
            $table->unsignedBigInteger('row_number')->nullable();
            $table->string('entity_public_id')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('error_code')->nullable();
            $table->string('exception_class')->nullable();
            $table->boolean('retryable')->nullable();
            $table->string('correlation_id');
            $table->timestampsTz();

            $table->index(['process_run_id', 'occurred_at']);
            $table->index(['severity', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index(['stage']);
            $table->index(['correlation_id']);
        });

        Schema::create(DatabaseTable::MANAGED_PROCESS_SCHEDULES, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('process_key');
            $table->string('module_key');
            $table->string('scope', 32);
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('timezone');
            $table->string('cron_expression')->nullable();
            $table->string('interval_key')->nullable();
            $table->jsonb('input_snapshot')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestampTz('next_due_at')->nullable();
            $table->foreignId('last_run_id')->nullable()->constrained(DatabaseTable::MANAGED_PROCESS_RUNS)->nullOnDelete();
            $table->string('overlap_policy', 32);
            $table->foreignId('created_by_user_id')->nullable()->constrained(DatabaseTable::USERS)->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained(DatabaseTable::USERS)->nullOnDelete();
            $table->text('reason');
            $table->timestampsTz();

            $table->index(['process_key', 'enabled', 'next_due_at']);
            $table->index(['module_key', 'enabled']);
            $table->index(['team_id', 'enabled']);
        });
    }

    public function down(): void
    {
        $this->dropModuleTables();
    }

    private function dropModuleTables(): void
    {
        Schema::dropIfExists(DatabaseTable::MANAGED_PROCESS_SCHEDULES);
        Schema::dropIfExists(DatabaseTable::MANAGED_PROCESS_LOG_EVENTS);
        Schema::dropIfExists(DatabaseTable::MANAGED_PROCESS_RUNS);
        Schema::dropIfExists(DatabaseTable::MANAGED_PROCESS_DEFINITIONS);
    }

    private function releaseLegacyReportProcessDependency(): void
    {
        if (! Schema::hasTable('optional_reports.export_requests')) {
            return;
        }

        DB::statement('alter table optional_reports.export_requests drop constraint if exists optional_reports_export_requests_process_run_id_foreign');
        DB::statement('update optional_reports.export_requests set process_run_id = null');
    }
};
