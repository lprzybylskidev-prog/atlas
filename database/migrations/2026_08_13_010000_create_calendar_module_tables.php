<?php

declare(strict_types=1);

use App\Modules\Core\Calendar\Infrastructure\Persistence\TableNames\CalendarDatabaseTable;
use App\Modules\Core\Identity\Infrastructure\Persistence\TableNames\IdentityDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::CORE_CALENDAR);

        Schema::create(CalendarDatabaseTable::PERSONAL_EVENTS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->boolean('all_day')->default(false);
            $table->string('location', 300)->nullable();
            $table->string('availability', 16)->default('busy');
            $table->string('recurrence_frequency', 16)->nullable();
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->jsonb('recurrence_weekdays')->nullable();
            $table->date('recurrence_ends_on')->nullable();
            $table->unsignedSmallInteger('recurrence_count')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->index(['user_id', 'starts_at']);
            $table->index(['user_id', 'recurrence_frequency']);
        });

        Schema::create(CalendarDatabaseTable::REMINDERS, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedInteger('minutes_before');
            $table->timestampsTz();

            $table->foreign('event_id')->references('id')->on(CalendarDatabaseTable::PERSONAL_EVENTS)->cascadeOnDelete();
            $table->unique(['event_id', 'minutes_before']);
        });

        Schema::create(CalendarDatabaseTable::RECURRENCE_EXCEPTIONS, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->date('occurrence_date');
            $table->boolean('cancelled')->default(false);
            $table->jsonb('override_payload')->nullable();
            $table->timestampsTz();

            $table->foreign('event_id')->references('id')->on(CalendarDatabaseTable::PERSONAL_EVENTS)->cascadeOnDelete();
            $table->unique(['event_id', 'occurrence_date']);
        });

        Schema::create(CalendarDatabaseTable::CONTRIBUTED_EVENTS, static function (Blueprint $table): void {
            $table->id();
            $table->string('source_module', 120);
            $table->ulid('source_event_public_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->boolean('all_day')->default(false);
            $table->string('location', 300)->nullable();
            $table->jsonb('participant_user_public_ids');
            $table->timestampsTz();

            $table->unique(['source_module', 'source_event_public_id']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create(CalendarDatabaseTable::USER_PREFERENCES, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedInteger('default_reminder_minutes')->default(15);
            $table->boolean('email_enabled')->default(true);
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CalendarDatabaseTable::USER_PREFERENCES);
        Schema::dropIfExists(CalendarDatabaseTable::CONTRIBUTED_EVENTS);
        Schema::dropIfExists(CalendarDatabaseTable::RECURRENCE_EXCEPTIONS);
        Schema::dropIfExists(CalendarDatabaseTable::REMINDERS);
        Schema::dropIfExists(CalendarDatabaseTable::PERSONAL_EVENTS);
    }
};
