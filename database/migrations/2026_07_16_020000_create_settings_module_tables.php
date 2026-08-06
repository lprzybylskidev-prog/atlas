<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Settings\Application\Public\Persistence\SettingsDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::CORE_SETTINGS);

        Schema::create(SettingsDatabaseTable::SETTINGS_GLOBAL_VALUES, static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value');
            $table->timestampsTz();
        });

        Schema::create(SettingsDatabaseTable::SETTINGS_TEAM_VALUES, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('key');
            $table->jsonb('value');
            $table->timestampsTz();

            $table->unique(['team_id', 'key']);
        });

        Schema::create(SettingsDatabaseTable::SETTINGS_USER_VALUES, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->string('key');
            $table->jsonb('value');
            $table->timestampsTz();

            $table->unique(['user_id', 'key']);
        });

        Schema::create(SettingsDatabaseTable::SETTINGS_SECURITY_VALUES, static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SettingsDatabaseTable::SETTINGS_SECURITY_VALUES);
        Schema::dropIfExists(SettingsDatabaseTable::SETTINGS_USER_VALUES);
        Schema::dropIfExists(SettingsDatabaseTable::SETTINGS_TEAM_VALUES);
        Schema::dropIfExists(SettingsDatabaseTable::SETTINGS_GLOBAL_VALUES);
    }
};
