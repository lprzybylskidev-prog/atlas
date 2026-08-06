<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(DatabaseTable::USERS, static function (Blueprint $table): void {
            $table->timestampTz('password_changed_at')->nullable()->after('first_password_set_at');
            $table->string('avatar_color', 7)->default('#0f766e')->after('account_sensitivity');
            $table->ulid('avatar_image_file_public_id')->nullable()->after('avatar_color');

            $table->index('password_changed_at');
        });

        DB::table(DatabaseTable::USERS)
            ->whereNull('password_changed_at')
            ->update([
                'password_changed_at' => DB::raw('coalesce(first_password_set_at, updated_at, created_at)'),
            ]);

        DB::table(DatabaseTable::USERS)
            ->whereNull('avatar_color')
            ->update([
                'avatar_color' => '#0f766e',
            ]);

        Schema::create(DatabaseTable::NOTIFICATION_EMAIL_ADDRESSES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('email');
            $table->boolean('primary')->default(false);
            $table->timestampTz('verified_at')->nullable();
            $table->string('verification_token_hash')->nullable();
            $table->timestampTz('verification_sent_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'team_id', 'verified_at']);
        });

        DB::statement('create unique index notification_email_addresses_user_team_email_unique on '.DatabaseTable::NOTIFICATION_EMAIL_ADDRESSES.' (user_id, coalesce(team_id, 0), email)');
        DB::statement('create unique index notification_email_addresses_primary_unique on '.DatabaseTable::NOTIFICATION_EMAIL_ADDRESSES.' (user_id, coalesce(team_id, 0)) where "primary" = true');

        Schema::create(DatabaseTable::NOTIFICATION_EMAIL_PREFERENCES, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_email_address_id')->constrained(DatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained(DatabaseTable::TEAMS)->restrictOnDelete();
            $table->string('notification_type');
            $table->boolean('enabled')->default(true);
            $table->timestampsTz();

            $table->index(['team_id', 'notification_type', 'enabled']);
        });
        DB::statement('create unique index notification_email_preferences_address_team_type_unique on '.DatabaseTable::NOTIFICATION_EMAIL_PREFERENCES.' (notification_email_address_id, coalesce(team_id, 0), notification_type)');
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTable::NOTIFICATION_EMAIL_PREFERENCES);
        Schema::dropIfExists(DatabaseTable::NOTIFICATION_EMAIL_ADDRESSES);

        Schema::table(DatabaseTable::USERS, static function (Blueprint $table): void {
            $table->dropIndex(['password_changed_at']);
            $table->dropColumn(['password_changed_at', 'avatar_color', 'avatar_image_file_public_id']);
        });
    }
};
