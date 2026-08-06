<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
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

        Schema::create(DatabaseTable::TABLE_SAVED_VIEWS, function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('table_key');
            $table->string('name', 80);
            $table->string('type', 16);
            $table->foreignId('owner_user_id')->nullable()->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->jsonb('state');
            $table->timestampsTz();

            $table->index(['table_key', 'type']);
            $table->index(['owner_user_id', 'table_key']);
            $table->index(['team_id', 'table_key']);
        });

        Schema::create(DatabaseTable::TABLE_SAVED_VIEW_DEFAULTS, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('table_key');
            $table->foreignId('table_saved_view_id')->constrained(DatabaseTable::TABLE_SAVED_VIEWS)->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['user_id', 'table_key']);
            $table->index(['team_id', 'table_key']);
        });

        DB::statement('alter table '.DatabaseTable::TABLE_SAVED_VIEWS." add constraint table_saved_views_type_check check (type in ('private', 'team', 'system'))");
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTable::TABLE_SAVED_VIEW_DEFAULTS);
        Schema::dropIfExists(DatabaseTable::TABLE_SAVED_VIEWS);
    }
};
