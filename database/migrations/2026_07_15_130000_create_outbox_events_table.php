<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('event_id')->unique();
            $table->string('event_type');
            $table->unsignedInteger('schema_version');
            $table->string('source_module');
            $table->jsonb('payload');
            $table->timestampTz('occurred_at');
            $table->ulid('correlation_id');
            $table->ulid('causation_id')->nullable();
            $table->string('status', 32);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('failure_details')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'next_attempt_at'], 'outbox_events_status_next_attempt_index');
            $table->index(['status', 'occurred_at'], 'outbox_events_status_occurred_index');
            $table->index(['source_module', 'event_type'], 'outbox_events_source_type_index');
            $table->index('published_at', 'outbox_events_published_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
