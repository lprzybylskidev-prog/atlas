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
        DatabaseSchema::ensure(DatabaseSchema::CORE_TEAMS);
        DatabaseSchema::ensure(DatabaseSchema::CORE_AUTHORIZATION);

        Schema::create(DatabaseTable::TEAMS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create(DatabaseTable::TEAM_USER_ASSIGNMENTS, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->boolean('is_head_manager')->default(false);
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_to')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id']);
            $table->index(['user_id', 'team_id']);
        });

        DB::statement('create unique index team_user_assignments_active_unique on '.DatabaseTable::TEAM_USER_ASSIGNMENTS.' (team_id, user_id) where valid_to is null');

        Schema::create(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->foreignId('manager_user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('report_user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained(DatabaseTable::USERS)->nullOnDelete();
            $table->foreignId('ended_by_user_id')->nullable()->constrained(DatabaseTable::USERS)->nullOnDelete();
            $table->text('reason');
            $table->text('end_reason')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'manager_user_id', 'valid_to']);
            $table->index(['team_id', 'report_user_id', 'valid_to']);
            $table->index(['team_id', 'valid_from', 'valid_to']);
        });

        DB::statement('alter table '.DatabaseTable::TEAM_MANAGER_RELATIONSHIPS.' add constraint team_manager_relationships_not_self_check check (manager_user_id <> report_user_id)');
        DB::statement('create unique index team_manager_relationships_active_unique on '.DatabaseTable::TEAM_MANAGER_RELATIONSHIPS.' (team_id, manager_user_id, report_user_id) where valid_to is null');

        Schema::create(DatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
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

        Schema::create(DatabaseTable::USER_ONBOARDING_PACKAGES, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('package_name');
            $table->timestamps();

            $table->unique(['user_id', 'team_id']);
            $table->index(['team_id', 'package_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTable::USER_ONBOARDING_PACKAGES);
        Schema::dropIfExists(DatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES);
        Schema::dropIfExists(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS);
        Schema::dropIfExists(DatabaseTable::TEAM_USER_ASSIGNMENTS);
        Schema::dropIfExists(DatabaseTable::TEAMS);
    }
};
