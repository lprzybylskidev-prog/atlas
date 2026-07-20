<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\DatabaseSchema;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::OPTIONAL_INTEGRATIONS);

        $this->dropModuleTables();

        Schema::create(DatabaseTable::INTEGRATION_CONNECTIONS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('integration_key')->unique();
            $table->string('name');
            $table->boolean('enabled')->default(false);
            $table->boolean('external_api_enabled')->default(false);
            $table->string('source_of_truth');
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->jsonb('configuration')->nullable();
            $table->timestampsTz();

            $table->index(['enabled', 'integration_key']);
            $table->index(['last_success_at', 'last_error_at']);
        });

        Schema::create(DatabaseTable::INTEGRATION_CREDENTIALS, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained(DatabaseTable::INTEGRATION_CONNECTIONS)->restrictOnDelete();
            $table->string('client_key');
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->jsonb('scopes');
            $table->jsonb('allowed_modules')->nullable();
            $table->boolean('external_api_enabled')->default(false);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['connection_id', 'client_key']);
            $table->index(['team_id', 'revoked_at']);
        });

        Schema::create(DatabaseTable::INTEGRATION_EXTERNAL_ID_MAPPINGS, function (Blueprint $table): void {
            $table->id();
            $table->string('integration_key');
            $table->string('source_system');
            $table->string('entity_type');
            $table->string('external_id');
            $table->string('internal_public_id');
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['integration_key', 'source_system', 'entity_type', 'external_id', 'team_id'], 'integration_external_id_unique');
            $table->index(['entity_type', 'internal_public_id']);
        });

        Schema::create(DatabaseTable::INTEGRATION_SYNC_RUNS, function (Blueprint $table): void {
            $table->id();
            $table->string('integration_key');
            $table->string('operation');
            $table->string('correlation_id');
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('status', 32);
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->text('message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['integration_key', 'operation', 'started_at']);
            $table->index(['status', 'started_at']);
            $table->index(['correlation_id']);
        });

        Schema::create(DatabaseTable::INTEGRATION_IDEMPOTENCY_KEYS, function (Blueprint $table): void {
            $table->id();
            $table->string('integration_key');
            $table->string('operation');
            $table->string('idempotency_key');
            $table->string('request_hash', 64);
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->boolean('completed')->default(false);
            $table->boolean('successful')->nullable();
            $table->jsonb('response_summary')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['integration_key', 'operation', 'idempotency_key'], 'integration_idempotency_unique');
            $table->index(['team_id', 'created_at']);
        });

        Schema::create(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS, function (Blueprint $table): void {
            $table->id();
            $table->string('integration_key');
            $table->string('operation');
            $table->string('state', 32);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestampTz('opened_until')->nullable();
            $table->timestampTz('last_failure_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestampsTz();

            $table->unique(['integration_key', 'operation'], 'integration_circuit_unique');
            $table->index(['state', 'opened_until']);
        });
    }

    public function down(): void
    {
        $this->dropModuleTables();
    }

    private function dropModuleTables(): void
    {
        Schema::dropIfExists(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS);
        Schema::dropIfExists(DatabaseTable::INTEGRATION_IDEMPOTENCY_KEYS);
        Schema::dropIfExists(DatabaseTable::INTEGRATION_SYNC_RUNS);
        Schema::dropIfExists(DatabaseTable::INTEGRATION_EXTERNAL_ID_MAPPINGS);
        Schema::dropIfExists(DatabaseTable::INTEGRATION_CREDENTIALS);
        Schema::dropIfExists(DatabaseTable::INTEGRATION_CONNECTIONS);
    }
};
