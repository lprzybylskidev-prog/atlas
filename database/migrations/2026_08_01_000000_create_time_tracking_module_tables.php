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
        DatabaseSchema::ensure(DatabaseSchema::OPTIONAL_TIME_TRACKING);

        if (Schema::hasTable(DatabaseTable::TIME_TRACKING_USER_TEAM_SETTINGS)) {
            DB::statement('drop schema if exists '.DatabaseSchema::OPTIONAL_TIME_TRACKING.' cascade');
            DatabaseSchema::ensure(DatabaseSchema::OPTIONAL_TIME_TRACKING);
        }

        Schema::create(DatabaseTable::TIME_TRACKING_USER_TEAM_SETTINGS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('team_user_assignment_id')->constrained(DatabaseTable::TEAM_USER_ASSIGNMENTS)->restrictOnDelete();
            $table->boolean('tracking_enabled')->default(false);
            $table->timestamps();

            $table->unique('team_user_assignment_id', 'tt_user_team_settings_assignment_unique');
            $table->index('tracking_enabled');
        });

        Schema::create(DatabaseTable::TIME_TRACKING_WORK_SESSIONS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('laravel_session_id');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedBigInteger('exact_seconds')->nullable();
            $table->string('closure_reason')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'started_at']);
            $table->index(['user_id', 'team_id', 'started_at']);
            $table->index(['laravel_session_id', 'ended_at']);
        });

        DB::statement('create unique index time_tracking_work_sessions_active_user_unique on '.DatabaseTable::TIME_TRACKING_WORK_SESSIONS.' (user_id) where ended_at is null');

        Schema::create(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('work_session_id')->constrained(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->restrictOnDelete();
            $table->string('module_key');
            $table->string('context_key');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedBigInteger('exact_seconds')->nullable();
            $table->timestamps();

            $table->index(['work_session_id', 'started_at']);
            $table->index(['module_key', 'started_at']);
        });

        DB::statement('create unique index time_tracking_module_segments_active_session_unique on '.DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS.' (work_session_id) where ended_at is null');

        Schema::create(DatabaseTable::TIME_TRACKING_BREAKS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('work_session_id')->constrained(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->restrictOnDelete();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedBigInteger('exact_seconds')->nullable();
            $table->string('closure_reason')->nullable();
            $table->boolean('requires_manager_review')->default(false);
            $table->timestamps();

            $table->index(['team_id', 'started_at']);
            $table->index(['user_id', 'started_at']);
        });

        DB::statement('create unique index time_tracking_breaks_active_user_unique on '.DatabaseTable::TIME_TRACKING_BREAKS.' (user_id) where ended_at is null');

        Schema::create(DatabaseTable::TIME_TRACKING_BREAK_REMINDERS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('break_id')->constrained(DatabaseTable::TIME_TRACKING_BREAKS)->restrictOnDelete();
            $table->string('reminder_type');
            $table->timestampTz('due_at');
            $table->timestampTz('recorded_at');
            $table->timestamps();

            $table->unique(['break_id', 'reminder_type'], 'tt_break_reminders_break_type_unique');
            $table->index(['reminder_type', 'due_at']);
        });

        Schema::create(DatabaseTable::TIME_TRACKING_OTHER_WORK_CATEGORIES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->string('category_key');
            $table->string('label_pl');
            $table->string('label_en');
            $table->text('description_pl')->nullable();
            $table->text('description_en')->nullable();
            $table->boolean('requires_comment')->default(false);
            $table->boolean('auto_approval_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'category_key'], 'tt_other_work_categories_scope_key_unique');
            $table->index(['scope_type', 'scope_id', 'is_active']);
        });

        Schema::create(DatabaseTable::TIME_TRACKING_OTHER_WORK, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('work_session_id')->constrained(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->restrictOnDelete();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('category_key')->nullable();
            $table->text('description');
            $table->text('end_note')->nullable();
            $table->string('approval_status')->default('pending');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedBigInteger('exact_seconds')->nullable();
            $table->string('closure_reason')->nullable();
            $table->boolean('requires_manager_review')->default(true);
            $table->timestamps();

            $table->index(['team_id', 'started_at']);
            $table->index(['user_id', 'approval_status']);
        });

        DB::statement('create unique index time_tracking_other_work_active_user_unique on '.DatabaseTable::TIME_TRACKING_OTHER_WORK.' (user_id) where ended_at is null');

        Schema::create(DatabaseTable::TIME_TRACKING_BREAK_POLICIES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->unsignedInteger('daily_limit_seconds');
            $table->unsignedInteger('maximum_single_break_seconds');
            $table->unsignedInteger('warning_before_maximum_seconds')->default(900);
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id'], 'tt_break_policies_scope_unique');
        });

        Schema::create(DatabaseTable::TIME_TRACKING_MAINTENANCE_WINDOWS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('kind');
            $table->string('status');
            $table->timestampTz('scheduled_start_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('return_grace_seconds')->default(600);
            $table->text('reason');
            $table->timestamps();

            $table->index(['status', 'scheduled_start_at']);
            $table->index(['started_at', 'completed_at']);
        });

        Schema::create(DatabaseTable::TIME_TRACKING_MAINTENANCE_AFFECTED_SESSIONS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('maintenance_window_id')->constrained(DatabaseTable::TIME_TRACKING_MAINTENANCE_WINDOWS)->restrictOnDelete();
            $table->foreignId('work_session_id')->constrained(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->restrictOnDelete();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->timestampTz('interrupted_at');
            $table->timestampTz('return_deadline_at')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->timestamps();

            $table->unique(['maintenance_window_id', 'work_session_id'], 'tt_maintenance_sessions_window_session_unique');
            $table->index(['user_id', 'return_deadline_at']);
        });

        Schema::create(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->foreignId('work_session_id')->nullable()->constrained(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->restrictOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status');
            $table->string('request_type');
            $table->text('description');
            $table->timestampTz('requested_at');
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create(DatabaseTable::TIME_TRACKING_CORRECTION_PROPOSALS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('correction_request_id')->constrained(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)->restrictOnDelete();
            $table->timestampTz('original_started_at')->nullable();
            $table->timestampTz('original_ended_at')->nullable();
            $table->unsignedBigInteger('original_exact_seconds')->nullable();
            $table->timestampTz('proposed_started_at')->nullable();
            $table->timestampTz('proposed_ended_at')->nullable();
            $table->unsignedBigInteger('proposed_exact_seconds')->nullable();
            $table->timestampTz('final_started_at')->nullable();
            $table->timestampTz('final_ended_at')->nullable();
            $table->unsignedBigInteger('final_exact_seconds')->nullable();
            $table->timestamps();

            $table->unique('correction_request_id', 'tt_correction_proposals_request_unique');
        });

        Schema::create(DatabaseTable::TIME_TRACKING_CORRECTION_HISTORY, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('correction_request_id')->constrained(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->index(['correction_request_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
        });

        Schema::create(DatabaseTable::TIME_TRACKING_CLOSED_PERIOD_OVERRIDES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('correction_request_id')->constrained(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->string('actor_scope');
            $table->boolean('admin_mode_confirmed')->default(false);
            $table->boolean('high_risk_reauthenticated')->default(false);
            $table->boolean('mfa_confirmed')->default(false);
            $table->boolean('before_after_preview_confirmed')->default(false);
            $table->text('reason');
            $table->timestampTz('authorized_at');
            $table->timestamps();

            $table->unique('correction_request_id', 'tt_closed_period_overrides_request_unique');
            $table->index(['actor_user_id', 'authorized_at']);
        });

        Schema::create(DatabaseTable::TIME_TRACKING_SETTLEMENT_SETTINGS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedTinyInteger('period_start_day')->default(10);
            $table->timestamps();
        });

        Schema::create(DatabaseTable::TIME_TRACKING_SETTLEMENT_PERIODS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status');
            $table->timestampTz('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['starts_on', 'ends_on'], 'tt_settlement_periods_dates_unique');
            $table->index(['status', 'ends_on']);
        });
    }

    public function down(): void
    {
        DB::statement('drop schema if exists '.DatabaseSchema::OPTIONAL_TIME_TRACKING.' cascade');
    }
};
