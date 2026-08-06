<?php

declare(strict_types=1);

use App\Modules\Core\Files\Application\Public\Persistence\FilesDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(FilesDatabaseTable::FILE_OBJECTS, function (Blueprint $table): void {
            $table->foreignId('acknowledged_by_user_id')->nullable()->after('deleted_at')->constrained(IdentityDatabaseTable::USERS)->nullOnDelete();
            $table->timestampTz('acknowledged_at')->nullable()->after('acknowledged_by_user_id');
            $table->text('acknowledgement_reason')->nullable()->after('acknowledged_at');
            $table->index('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table(FilesDatabaseTable::FILE_OBJECTS, function (Blueprint $table): void {
            $table->dropIndex(['acknowledged_at']);
            $table->dropConstrainedForeignId('acknowledged_by_user_id');
            $table->dropColumn(['acknowledged_at', 'acknowledgement_reason']);
        });
    }
};
