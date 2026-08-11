<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Infrastructure\Persistence\TableNames\IdentityDatabaseTable;
use App\Modules\Core\Notifications\Infrastructure\Persistence\TableNames\NotificationsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        Schema::create(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('email');
            $table->boolean('primary')->default(false);
            $table->timestampTz('verified_at')->nullable();
            $table->string('verification_token_hash')->nullable();
            $table->timestampTz('verification_sent_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'team_id', 'verified_at']);
        });

        DB::statement('create unique index notification_email_addresses_user_team_email_unique on '.NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES.' (user_id, coalesce(team_id, 0), email)');
        DB::statement('create unique index notification_email_addresses_primary_unique on '.NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES.' (user_id, coalesce(team_id, 0)) where "primary" = true');

        Schema::create(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_email_address_id')->constrained(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(TeamsDatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('notification_type');
            $table->boolean('enabled')->default(true);
            $table->timestampsTz();

            $table->index(['team_id', 'notification_type', 'enabled']);
        });

        DB::statement('create unique index notification_email_preferences_address_team_type_unique on '.NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES.' (notification_email_address_id, coalesce(team_id, 0), notification_type)');

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
        Schema::dropIfExists(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES);
        Schema::dropIfExists(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES);
        Schema::dropIfExists(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS);
        Schema::dropIfExists(NotificationsDatabaseTable::NOTIFICATIONS);
    }
};
