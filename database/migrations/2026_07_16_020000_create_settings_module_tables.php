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
        DatabaseSchema::ensure(DatabaseSchema::CORE_SETTINGS);

        Schema::create(DatabaseTable::SETTINGS_GLOBAL_VALUES, static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value');
            $table->timestampsTz();
        });

        Schema::create(DatabaseTable::SETTINGS_TEAM_VALUES, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('key');
            $table->jsonb('value');
            $table->timestampsTz();

            $table->unique(['team_id', 'key']);
        });

        Schema::create(DatabaseTable::SETTINGS_USER_VALUES, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->string('key');
            $table->jsonb('value');
            $table->timestampsTz();

            $table->unique(['user_id', 'key']);
        });

        Schema::create(DatabaseTable::SETTINGS_SECURITY_VALUES, static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTable::SETTINGS_SECURITY_VALUES);
        Schema::dropIfExists(DatabaseTable::SETTINGS_USER_VALUES);
        Schema::dropIfExists(DatabaseTable::SETTINGS_TEAM_VALUES);
        Schema::dropIfExists(DatabaseTable::SETTINGS_GLOBAL_VALUES);
    }
};
