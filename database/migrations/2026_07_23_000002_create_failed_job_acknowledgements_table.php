<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
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

        Schema::create(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('failed_job_uuid')->unique();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained(IdentityDatabaseTable::USERS)->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->index('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS);
    }
};
