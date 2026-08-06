<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Notifications\Application\Public\Persistence\NotificationsDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::CORE_NOTIFICATIONS);

        Schema::create(NotificationsDatabaseTable::NOTIFICATIONS, function (Blueprint $table): void {
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

        Schema::create(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained(NotificationsDatabaseTable::NOTIFICATIONS)->restrictOnDelete();
            $table->foreignId('user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('delivered_in_app_at')->nullable();
            $table->timestampTz('delivered_email_at')->nullable();
            $table->string('email_status')->default('not_requested');
            $table->timestampsTz();

            $table->unique(['notification_id', 'user_id', 'team_id']);
            $table->index(['user_id', 'team_id', 'read_at', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create(NotificationsDatabaseTable::REALTIME_EVENTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('topic');
            $table->string('event_type');
            $table->foreignId('user_id')->nullable()->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
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
        Schema::dropIfExists(NotificationsDatabaseTable::REALTIME_EVENTS);
        Schema::dropIfExists(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS);
        Schema::dropIfExists(NotificationsDatabaseTable::NOTIFICATIONS);
    }
};
