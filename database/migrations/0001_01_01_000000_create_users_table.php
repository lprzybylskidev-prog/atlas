<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Infrastructure\Persistence\TableNames\IdentityDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::CORE_IDENTITY);

        Schema::create(IdentityDatabaseTable::USERS, function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->timestampTz('first_password_set_at')->nullable();
            $table->timestampTz('password_changed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('deactivated_at')->nullable();
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->unsignedSmallInteger('login_lock_count')->default(0);
            $table->timestampTz('login_locked_until')->nullable();
            $table->string('account_sensitivity', 32)->default('normal');
            $table->string('avatar_color', 7)->default('#0f766e');
            $table->ulid('avatar_image_file_public_id')->nullable();
            $table->rememberToken();
            $table->timestampsTz();

            $table->index(['is_active', 'email']);
            $table->index('login_locked_until');
            $table->index('account_sensitivity');
            $table->index('password_changed_at');
        });

        Schema::create(IdentityDatabaseTable::PASSWORD_RESET_TOKENS, function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create(IdentityDatabaseTable::USER_PASSWORD_HISTORIES, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->string('password_hash');
            $table->timestampTz('created_at');

            $table->index(['user_id', 'created_at']);
        });

        Schema::create(IdentityDatabaseTable::SESSIONS, function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained(IdentityDatabaseTable::USERS)->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(IdentityDatabaseTable::SESSIONS);
        Schema::dropIfExists(IdentityDatabaseTable::USER_PASSWORD_HISTORIES);
        Schema::dropIfExists(IdentityDatabaseTable::PASSWORD_RESET_TOKENS);
        Schema::dropIfExists(IdentityDatabaseTable::USERS);
    }
};
