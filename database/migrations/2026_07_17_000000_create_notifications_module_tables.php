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
        DatabaseSchema::ensure(DatabaseSchema::CORE_NOTIFICATIONS);

        Schema::create(DatabaseTable::NOTIFICATIONS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('type');
            $table->string('severity')->default('info');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('deep_link_url')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestampsTz();

            $table->index(['type', 'created_at']);
            $table->index(['severity', 'created_at']);
        });

        Schema::create(DatabaseTable::NOTIFICATION_RECIPIENTS, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained(DatabaseTable::NOTIFICATIONS)->restrictOnDelete();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('delivered_in_app_at')->nullable();
            $table->timestampTz('delivered_email_at')->nullable();
            $table->string('email_status')->default('not_requested');
            $table->timestampsTz();

            $table->unique(['notification_id', 'user_id', 'team_id']);
            $table->index(['user_id', 'team_id', 'read_at', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create(DatabaseTable::NOTIFICATION_PREFERENCES, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->string('type');
            $table->string('channel');
            $table->boolean('enabled')->default(true);
            $table->timestampsTz();

            $table->unique(['user_id', 'type', 'channel']);
            $table->index(['user_id', 'channel']);
        });

        Schema::create(DatabaseTable::REALTIME_EVENTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('topic');
            $table->string('event_type');
            $table->foreignId('user_id')->nullable()->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->jsonb('payload');
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['topic', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
            $table->index(['published_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTable::REALTIME_EVENTS);
        Schema::dropIfExists(DatabaseTable::NOTIFICATION_PREFERENCES);
        Schema::dropIfExists(DatabaseTable::NOTIFICATION_RECIPIENTS);
        Schema::dropIfExists(DatabaseTable::NOTIFICATIONS);
    }
};
