<?php

declare(strict_types=1);

use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Infrastructure\Persistence\TableNames\IdentityDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::CORE_TEAMS);
        DatabaseSchema::ensure(DatabaseSchema::CORE_AUTHORIZATION);

        Schema::create(TeamsDatabaseTable::TEAMS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->unsignedSmallInteger('inactivity_timeout_minutes')->nullable();
            $table->unsignedSmallInteger('session_max_lifetime_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->foreignId('user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->boolean('is_head_manager')->default(false);
            $table->unsignedSmallInteger('inactivity_timeout_minutes')->nullable();
            $table->unsignedSmallInteger('session_max_lifetime_minutes')->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_to')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id']);
            $table->index(['user_id', 'team_id']);
        });

        DB::statement('create unique index team_user_assignments_active_unique on '.TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS.' (team_id, user_id) where valid_to is null');

        Schema::create(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('team_id')->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->foreignId('manager_user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('report_user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained(IdentityDatabaseTable::USERS)->nullOnDelete();
            $table->foreignId('ended_by_user_id')->nullable()->constrained(IdentityDatabaseTable::USERS)->nullOnDelete();
            $table->text('reason');
            $table->text('end_reason')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'manager_user_id', 'valid_to']);
            $table->index(['team_id', 'report_user_id', 'valid_to']);
            $table->index(['team_id', 'valid_from', 'valid_to']);
        });

        DB::statement('alter table '.TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS.' add constraint team_manager_relationships_not_self_check check (manager_user_id <> report_user_id)');
        DB::statement('create unique index team_manager_relationships_active_unique on '.TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS.' (team_id, manager_user_id, report_user_id) where valid_to is null');

        Schema::create(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('team_id')->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('name');
            $table->string('label');
            $table->json('initial_role_names');
            $table->json('direct_permission_names');
            $table->json('template_permission_names');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'is_active']);
        });

        Schema::create(AuthorizationDatabaseTable::USER_ONBOARDING_PACKAGES, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('package_name');
            $table->timestamps();

            $table->unique(['user_id', 'team_id']);
            $table->index(['team_id', 'package_name']);
        });

        Schema::create(AuthorizationDatabaseTable::USER_TEAM_ASSIGNMENT_PROVENANCE, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('source_type');
            $table->ulid('source_public_id')->nullable();
            $table->string('source_display_name_snapshot')->nullable();
            $table->foreignId('copied_from_user_id')->nullable()->constrained(IdentityDatabaseTable::USERS)->nullOnDelete();
            $table->unsignedInteger('preset_version')->nullable();
            $table->json('preset_snapshot')->nullable();
            $table->foreignId('applied_by_user_id')->nullable()->constrained(IdentityDatabaseTable::USERS)->nullOnDelete();
            $table->timestampTz('applied_at');
            $table->text('reason');
            $table->json('resulting_role_names');
            $table->json('resulting_direct_permission_names');
            $table->json('resulting_limits');
            $table->foreignId('updated_by_user_id')->nullable()->constrained(IdentityDatabaseTable::USERS)->nullOnDelete();
            $table->text('update_reason')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'team_id']);
            $table->index(['team_id', 'source_type']);
            $table->index(['copied_from_user_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(AuthorizationDatabaseTable::USER_TEAM_ASSIGNMENT_PROVENANCE);
        Schema::dropIfExists(AuthorizationDatabaseTable::USER_ONBOARDING_PACKAGES);
        Schema::dropIfExists(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES);
        Schema::dropIfExists(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS);
        Schema::dropIfExists(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS);
        Schema::dropIfExists(TeamsDatabaseTable::TEAMS);
    }
};
