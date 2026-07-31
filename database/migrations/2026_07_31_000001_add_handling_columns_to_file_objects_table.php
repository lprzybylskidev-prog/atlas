<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(DatabaseTable::FILE_OBJECTS, function (Blueprint $table): void {
            $table->foreignId('acknowledged_by_user_id')->nullable()->after('deleted_at')->constrained(DatabaseTable::USERS)->nullOnDelete();
            $table->timestampTz('acknowledged_at')->nullable()->after('acknowledged_by_user_id');
            $table->text('acknowledgement_reason')->nullable()->after('acknowledged_at');
            $table->index('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table(DatabaseTable::FILE_OBJECTS, function (Blueprint $table): void {
            $table->dropIndex(['acknowledged_at']);
            $table->dropConstrainedForeignId('acknowledged_by_user_id');
            $table->dropColumn(['acknowledged_at', 'acknowledgement_reason']);
        });
    }
};
