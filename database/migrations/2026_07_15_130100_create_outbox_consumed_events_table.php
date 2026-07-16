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

        Schema::create(DatabaseTable::OUTBOX_CONSUMED_EVENTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('event_id');
            $table->string('consumer');
            $table->timestampTz('consumed_at');
            $table->timestampsTz();

            $table->unique(['event_id', 'consumer'], 'outbox_consumed_events_event_consumer_unique');
            $table->index('consumer', 'outbox_consumed_events_consumer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTable::OUTBOX_CONSUMED_EVENTS);
    }
};
