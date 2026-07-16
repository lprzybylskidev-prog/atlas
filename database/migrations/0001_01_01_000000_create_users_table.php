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
        DatabaseSchema::ensure(DatabaseSchema::CORE_IDENTITY);

        Schema::create(DatabaseTable::USERS, function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestampTz('first_password_set_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('deactivated_at')->nullable();
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->unsignedSmallInteger('login_lock_count')->default(0);
            $table->timestampTz('login_locked_until')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['is_active', 'email']);
            $table->index('login_locked_until');
        });

        Schema::create(DatabaseTable::PASSWORD_RESET_TOKENS, function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create(DatabaseTable::USER_PASSWORD_HISTORIES, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(DatabaseTable::USERS)->restrictOnDelete();
            $table->string('password_hash');
            $table->timestampTz('created_at');

            $table->index(['user_id', 'created_at']);
        });

        Schema::create(DatabaseTable::USER_WEBAUTHN_CREDENTIALS, function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->ulid('user_public_id');
            $table->string('label');
            $table->text('credential_id')->unique();
            $table->string('type');
            $table->jsonb('transports');
            $table->string('attestation_type');
            $table->uuid('aaguid');
            $table->text('credential_public_key');
            $table->text('user_handle');
            $table->unsignedBigInteger('counter')->default(0);
            $table->boolean('backup_eligible')->nullable();
            $table->boolean('backup_status')->nullable();
            $table->boolean('uv_initialized')->nullable();
            $table->boolean('hardware_backed')->default(false);
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_public_id', 'created_at']);
            $table->index('hardware_backed');
        });

        Schema::create(DatabaseTable::SESSIONS, function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTable::SESSIONS);
        Schema::dropIfExists(DatabaseTable::USER_WEBAUTHN_CREDENTIALS);
        Schema::dropIfExists(DatabaseTable::USER_PASSWORD_HISTORIES);
        Schema::dropIfExists(DatabaseTable::PASSWORD_RESET_TOKENS);
        Schema::dropIfExists(DatabaseTable::USERS);
    }
};
