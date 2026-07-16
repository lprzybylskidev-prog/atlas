<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_saved_views', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('table_key');
            $table->string('name', 80);
            $table->string('type', 16);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->restrictOnDelete();
            $table->jsonb('state');
            $table->timestampsTz();

            $table->index(['table_key', 'type']);
            $table->index(['owner_user_id', 'table_key']);
            $table->index(['team_id', 'table_key']);
        });

        Schema::create('table_saved_view_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->restrictOnDelete();
            $table->string('table_key');
            $table->foreignId('table_saved_view_id')->constrained('table_saved_views')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['user_id', 'table_key']);
            $table->index(['team_id', 'table_key']);
        });

        DB::statement("alter table table_saved_views add constraint table_saved_views_type_check check (type in ('private', 'team', 'system'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('table_saved_view_defaults');
        Schema::dropIfExists('table_saved_views');
    }
};
