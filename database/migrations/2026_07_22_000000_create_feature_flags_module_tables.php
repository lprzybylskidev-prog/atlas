<?php

declare(strict_types=1);

use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\FeatureFlags\Application\Public\Persistence\FeatureFlagsDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::OPTIONAL_FEATURE_FLAGS);

        $this->dropModuleTables();

        Schema::create(FeatureFlagsDatabaseTable::GLOBAL_VALUES, function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->ulid('public_id')->unique();
            $table->string('flag_key', 160)->unique();
            $table->jsonb('value');
            $table->string('updated_by_public_id', 26);
            $table->text('updated_reason');
            $table->timestampsTz();

            $table->index('updated_at');
        });

        Schema::create(FeatureFlagsDatabaseTable::TEAM_VALUES, function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->ulid('public_id')->unique();
            $table->string('flag_key', 160);
            $table->foreignId('team_id')->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->jsonb('value');
            $table->string('updated_by_public_id', 26);
            $table->text('updated_reason');
            $table->timestampsTz();

            $table->unique(['flag_key', 'team_id']);
            $table->index('updated_at');
        });

        Schema::create(FeatureFlagsDatabaseTable::HISTORY, function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->ulid('public_id')->unique();
            $table->string('flag_key', 160);
            $table->string('scope', 20);
            $table->foreignId('team_id')->nullable()->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('action', 120);
            $table->jsonb('before_value')->nullable();
            $table->jsonb('after_value')->nullable();
            $table->string('actor_public_id', 26);
            $table->text('reason');
            $table->timestampTz('created_at');

            $table->index(['flag_key', 'created_at']);
            $table->index(['scope', 'created_at']);
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $this->dropModuleTables();
    }

    private function dropModuleTables(): void
    {
        Schema::dropIfExists(FeatureFlagsDatabaseTable::HISTORY);
        Schema::dropIfExists(FeatureFlagsDatabaseTable::TEAM_VALUES);
        Schema::dropIfExists(FeatureFlagsDatabaseTable::GLOBAL_VALUES);
    }
};
