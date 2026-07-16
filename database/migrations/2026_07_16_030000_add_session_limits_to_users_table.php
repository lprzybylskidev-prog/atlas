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
        Schema::table(DatabaseTable::USERS, static function (Blueprint $table): void {
            $table->unsignedSmallInteger('inactivity_timeout_minutes')->nullable()->after('login_locked_until');
            $table->unsignedSmallInteger('session_max_lifetime_minutes')->nullable()->after('inactivity_timeout_minutes');
        });
    }

    public function down(): void
    {
        Schema::table(DatabaseTable::USERS, static function (Blueprint $table): void {
            $table->dropColumn(['inactivity_timeout_minutes', 'session_max_lifetime_minutes']);
        });
    }
};
