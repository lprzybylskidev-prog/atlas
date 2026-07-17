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
        DatabaseSchema::ensure(DatabaseSchema::SHARED);

        Schema::create(DatabaseTable::SCHEDULER_HEARTBEATS, static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('status');
            $table->timestampTz('last_started_at')->nullable();
            $table->timestampTz('last_finished_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_failed_at')->nullable();
            $table->unsignedInteger('last_runtime_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'last_success_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTable::SCHEDULER_HEARTBEATS);
    }
};
