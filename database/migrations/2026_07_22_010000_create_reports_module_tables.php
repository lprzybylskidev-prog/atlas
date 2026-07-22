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
        DatabaseSchema::ensure(DatabaseSchema::OPTIONAL_REPORTS);

        $this->dropModuleTables();

        Schema::create(DatabaseTable::REPORT_EXPORT_REQUESTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('report_key', 160);
            $table->string('report_name');
            $table->string('module_key');
            $table->string('format', 32);
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('active_team_public_id', 26)->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained(DatabaseTable::USERS)->nullOnDelete();
            $table->string('requesting_user_public_id', 26);
            $table->jsonb('filters');
            $table->jsonb('sorting');
            $table->jsonb('visible_columns');
            $table->jsonb('column_order');
            $table->jsonb('time_range')->nullable();
            $table->jsonb('authorization_snapshot');
            $table->string('authorization_fingerprint', 64);
            $table->string('request_fingerprint', 64)->unique();
            $table->string('release_version');
            $table->string('rule_version');
            $table->string('status', 32);
            $table->boolean('synchronous_allowed')->default(false);
            $table->boolean('audit_export')->default(false);
            $table->unsignedBigInteger('estimated_row_count')->nullable();
            $table->foreignId('process_run_id')->nullable()->constrained(DatabaseTable::MANAGED_PROCESS_RUNS)->nullOnDelete();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('safe_error_summary')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index(['report_key', 'status']);
            $table->index(['module_key', 'status']);
            $table->index(['team_id', 'status']);
            $table->index(['requested_by_user_id', 'status']);
            $table->index(['format', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index(['synchronous_allowed', 'format']);
        });

        Schema::create(DatabaseTable::REPORT_EXPORT_ARTIFACTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('export_request_id')->constrained(DatabaseTable::REPORT_EXPORT_REQUESTS)->restrictOnDelete();
            $table->foreignId('file_object_id')->nullable()->constrained(DatabaseTable::FILE_OBJECTS)->restrictOnDelete();
            $table->string('file_object_public_id', 26)->nullable();
            $table->string('status', 32);
            $table->string('filename');
            $table->string('content_type');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum_sha256', 64)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained(DatabaseTable::USERS)->nullOnDelete();
            $table->timestampTz('available_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->unique(['export_request_id', 'status'], 'report_export_artifacts_request_status_unique');
            $table->index(['file_object_id']);
            $table->index(['file_object_public_id']);
            $table->index(['status', 'expires_at']);
            $table->index(['created_by_user_id', 'status']);
        });

        DB::statement(sprintf(
            "alter table %s add constraint report_export_artifacts_available_complete_check check (status <> 'available' or (file_object_id is not null and file_object_public_id is not null and file_object_public_id <> '' and checksum_sha256 is not null and checksum_sha256 <> '' and size_bytes > 0 and available_at is not null and failed_at is null))",
            DatabaseTable::REPORT_EXPORT_ARTIFACTS,
        ));
        DB::statement(sprintf(
            "create unique index report_export_artifacts_one_available_per_request on %s (export_request_id) where status = 'available'",
            DatabaseTable::REPORT_EXPORT_ARTIFACTS,
        ));

        Schema::create(DatabaseTable::REPORT_RENDER_CREDENTIALS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('export_request_id')->constrained(DatabaseTable::REPORT_EXPORT_REQUESTS)->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('requested_by_user_id')->nullable()->constrained(DatabaseTable::USERS)->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('module_key');
            $table->string('report_key', 160);
            $table->jsonb('allowed_dataset');
            $table->jsonb('allowed_columns');
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['export_request_id', 'consumed_at']);
            $table->index(['team_id', 'expires_at']);
            $table->index(['module_key', 'report_key']);
        });
    }

    public function down(): void
    {
        $this->dropModuleTables();
    }

    private function dropModuleTables(): void
    {
        Schema::dropIfExists(DatabaseTable::REPORT_RENDER_CREDENTIALS);
        Schema::dropIfExists(DatabaseTable::REPORT_EXPORT_ARTIFACTS);
        Schema::dropIfExists(DatabaseTable::REPORT_EXPORT_REQUESTS);
    }
};
