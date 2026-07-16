<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings_global_values', static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value');
            $table->timestampsTz();
        });

        Schema::create('settings_team_values', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->string('key');
            $table->jsonb('value');
            $table->timestampsTz();

            $table->unique(['team_id', 'key']);
        });

        Schema::create('settings_user_values', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('key');
            $table->jsonb('value');
            $table->timestampsTz();

            $table->unique(['user_id', 'key']);
        });

        Schema::create('settings_security_values', static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_security_values');
        Schema::dropIfExists('settings_user_values');
        Schema::dropIfExists('settings_team_values');
        Schema::dropIfExists('settings_global_values');
    }
};
