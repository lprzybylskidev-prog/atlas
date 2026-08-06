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
        Schema::table(DatabaseTable::USERS, function (Blueprint $table): void {
            $table->string('account_sensitivity', 32)->default('normal')->after('login_locked_until');
            $table->index('account_sensitivity');
        });
    }

    public function down(): void
    {
        Schema::table(DatabaseTable::USERS, function (Blueprint $table): void {
            $table->dropIndex(['account_sensitivity']);
            $table->dropColumn('account_sensitivity');
        });
    }
};
