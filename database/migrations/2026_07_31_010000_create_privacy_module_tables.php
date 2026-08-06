<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Privacy\Application\Public\Persistence\PrivacyDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::CORE_PRIVACY);

        Schema::dropIfExists(PrivacyDatabaseTable::LEGAL_HOLDS);
        Schema::dropIfExists(PrivacyDatabaseTable::OPERATION_PREVIEWS);
        Schema::dropIfExists(PrivacyDatabaseTable::OPERATION_REQUESTS);

        Schema::create(PrivacyDatabaseTable::OPERATION_REQUESTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('operation', 40);
            $table->string('subject_type', 80);
            $table->string('subject_identifier', 120);
            $table->string('status', 40);
            $table->boolean('dry_run')->default(true);
            $table->foreignId('requested_by_user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->text('reason');
            $table->string('confirmation_phrase');
            $table->string('correlation_id')->nullable();
            $table->timestampTz('previewed_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('executed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['operation', 'status', 'created_at']);
            $table->index(['subject_type', 'subject_identifier']);
            $table->index(['requested_by_user_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
        });

        Schema::create(PrivacyDatabaseTable::OPERATION_PREVIEWS, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_request_id')->constrained(PrivacyDatabaseTable::OPERATION_REQUESTS)->restrictOnDelete();
            $table->jsonb('impacts');
            $table->jsonb('blockers');
            $table->unsignedInteger('participant_count');
            $table->unsignedInteger('estimated_records');
            $table->boolean('can_execute');
            $table->timestampTz('created_at');

            $table->index(['operation_request_id', 'created_at']);
            $table->index(['can_execute', 'created_at']);
        });

        Schema::create(PrivacyDatabaseTable::LEGAL_HOLDS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('subject_type', 80);
            $table->string('subject_identifier', 120);
            $table->foreignId('created_by_user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->text('reason');
            $table->date('expires_on')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->text('release_reason')->nullable();
            $table->timestampsTz();

            $table->index(['subject_type', 'subject_identifier']);
            $table->index(['team_id', 'created_at']);
            $table->index(['expires_on', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(PrivacyDatabaseTable::LEGAL_HOLDS);
        Schema::dropIfExists(PrivacyDatabaseTable::OPERATION_PREVIEWS);
        Schema::dropIfExists(PrivacyDatabaseTable::OPERATION_REQUESTS);
    }
};
