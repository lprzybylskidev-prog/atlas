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
        DatabaseSchema::ensure(DatabaseSchema::SHARED);

        Schema::create(DatabaseTable::MODULE_GLOBAL_STATES, static function (Blueprint $table): void {
            $table->id();
            $table->string('module_key')->unique();
            $table->boolean('enabled');
            $table->timestampTz('enabled_from')->nullable();
            $table->timestampTz('disabled_from')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->text('reason');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
        });

        Schema::create(DatabaseTable::MODULE_TEAM_STATES, static function (Blueprint $table): void {
            $table->id();
            $table->string('module_key');
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->boolean('enabled');
            $table->timestampTz('enabled_from')->nullable();
            $table->timestampTz('disabled_from')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->text('reason');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->unique(['module_key', 'team_id']);
            $table->index(['team_id', 'enabled']);
        });

        Schema::create(DatabaseTable::MODULE_ACTIVATION_SCHEDULES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('module_key');
            $table->string('scope');
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->boolean('target_enabled');
            $table->timestampTz('effective_at');
            $table->string('status');
            $table->foreignId('creator_user_id')->nullable()->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('cancellation_actor_user_id')->nullable()->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->text('reason');
            $table->text('cancellation_reason')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->index(['module_key', 'scope', 'team_id', 'effective_at']);
            $table->index(['status', 'effective_at']);
        });

        Schema::create(DatabaseTable::MODULE_ACTIVATION_HISTORY, static function (Blueprint $table): void {
            $table->id();
            $table->string('module_key');
            $table->string('scope');
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->boolean('previous_enabled')->nullable();
            $table->boolean('new_enabled');
            $table->string('source');
            $table->foreignId('schedule_id')->nullable()->constrained(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->text('reason');
            $table->timestampTz('effective_at');
            $table->string('correlation_id')->nullable();
            $table->timestampsTz();

            $table->index(['module_key', 'scope', 'team_id', 'effective_at']);
            $table->index(['correlation_id', 'effective_at']);
        });

        DB::statement($this->appendOnlyTriggerSql('module_activation_history', DatabaseTable::MODULE_ACTIVATION_HISTORY));
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists module_activation_history_append_only on '.DatabaseTable::MODULE_ACTIVATION_HISTORY);

        Schema::dropIfExists(DatabaseTable::MODULE_ACTIVATION_HISTORY);
        Schema::dropIfExists(DatabaseTable::MODULE_ACTIVATION_SCHEDULES);
        Schema::dropIfExists(DatabaseTable::MODULE_TEAM_STATES);
        Schema::dropIfExists(DatabaseTable::MODULE_GLOBAL_STATES);
    }

    private function appendOnlyTriggerSql(string $triggerBaseName, string $table): string
    {
        return sprintf(
            'create trigger %s_append_only before update or delete on %s for each row execute function prevent_audit_mutation()',
            $triggerBaseName,
            $table,
        );
    }
};
