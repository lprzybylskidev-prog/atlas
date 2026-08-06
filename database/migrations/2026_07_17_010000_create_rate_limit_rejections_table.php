<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::CORE_IDENTITY);

        Schema::create(IdentityDatabaseTable::RATE_LIMIT_REJECTIONS, function (Blueprint $table): void {
            $table->id();
            $table->string('policy');
            $table->string('limiter_key_hash', 64);
            $table->string('limiter_key_preview', 180);
            $table->unsignedInteger('rejections_count')->default(0);
            $table->timestampTz('first_rejected_at');
            $table->timestampTz('last_rejected_at');
            $table->string('last_request_id')->nullable();
            $table->timestampsTz();

            $table->unique(['policy', 'limiter_key_hash']);
            $table->index(['policy', 'last_rejected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(IdentityDatabaseTable::RATE_LIMIT_REJECTIONS);
    }
};
