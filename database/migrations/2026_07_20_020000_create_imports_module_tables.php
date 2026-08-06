<?php

declare(strict_types=1);

use App\Modules\Core\Files\Application\Public\Persistence\FilesDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\Imports\Application\Public\Persistence\ImportsDatabaseTable;
use App\Modules\Optional\ManagedProcesses\Application\Public\Persistence\ManagedProcessesDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::OPTIONAL_IMPORTS);

        $this->dropModuleTables();

        Schema::create(ImportsDatabaseTable::EXECUTIONS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('process_run_id')->constrained(ManagedProcessesDatabaseTable::RUNS)->restrictOnDelete();
            $table->string('import_key');
            $table->string('source_type', 32);
            $table->foreignId('file_object_id')->nullable()->constrained(FilesDatabaseTable::FILE_OBJECTS)->restrictOnDelete();
            $table->string('api_reference')->nullable();
            $table->string('external_reference')->nullable();
            $table->jsonb('mapping_snapshot')->nullable();
            $table->jsonb('source_metadata');
            $table->jsonb('statistics');
            $table->string('idempotency_key')->nullable();
            $table->string('idempotency_state', 32);
            $table->timestampsTz();

            $table->unique(['process_run_id']);
            $table->index(['import_key', 'source_type']);
            $table->index(['idempotency_key', 'idempotency_state']);
        });

        Schema::create(ImportsDatabaseTable::ROW_ERRORS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('import_execution_id')->constrained(ImportsDatabaseTable::EXECUTIONS)->restrictOnDelete();
            $table->unsignedBigInteger('row_number')->nullable();
            $table->string('field_name')->nullable();
            $table->string('severity', 16);
            $table->string('error_code');
            $table->text('message');
            $table->jsonb('safe_context')->nullable();
            $table->timestampsTz();

            $table->index(['import_execution_id', 'row_number']);
            $table->index(['severity', 'error_code']);
        });

        Schema::create(ImportsDatabaseTable::IDEMPOTENCY_KEYS, function (Blueprint $table): void {
            $table->id();
            $table->string('import_key');
            $table->string('idempotency_key');
            $table->string('request_hash', 64);
            $table->foreignId('team_id')->nullable()->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->foreignId('process_run_id')->nullable()->constrained(ManagedProcessesDatabaseTable::RUNS)->nullOnDelete();
            $table->string('state', 32);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['import_key', 'idempotency_key']);
            $table->index(['team_id', 'state']);
        });
    }

    public function down(): void
    {
        $this->dropModuleTables();
    }

    private function dropModuleTables(): void
    {
        Schema::dropIfExists(ImportsDatabaseTable::IDEMPOTENCY_KEYS);
        Schema::dropIfExists(ImportsDatabaseTable::ROW_ERRORS);
        Schema::dropIfExists(ImportsDatabaseTable::EXECUTIONS);
    }
};
