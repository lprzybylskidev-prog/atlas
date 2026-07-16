<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('team_user_assignments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_head_manager')->default(false);
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_to')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
            $table->index(['user_id', 'team_id']);
        });

        Schema::create('authorization_onboarding_packages', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name')->unique();
            $table->string('label');
            $table->json('initial_role_names');
            $table->json('direct_permission_names');
            $table->json('template_permission_names');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_onboarding_packages', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->string('package_name');
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['team_id', 'package_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboarding_packages');
        Schema::dropIfExists('authorization_onboarding_packages');
        Schema::dropIfExists('team_user_assignments');
        Schema::dropIfExists('teams');
    }
};
