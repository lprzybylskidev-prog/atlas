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
        Schema::table(DatabaseTable::ROLES, static function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('name');
        });

        Schema::table(DatabaseTable::PERMISSIONS, static function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('name');
        });

        Schema::table(DatabaseTable::TEAMS, static function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('name');
        });

        DB::table(DatabaseTable::ROLES)->whereNull('display_name')->update(['display_name' => DB::raw('name')]);
        DB::table(DatabaseTable::PERMISSIONS)->whereNull('display_name')->update(['display_name' => DB::raw('name')]);
        DB::table(DatabaseTable::TEAMS)->whereNull('display_name')->update(['display_name' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table(DatabaseTable::TEAMS, static function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });

        Schema::table(DatabaseTable::PERMISSIONS, static function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });

        Schema::table(DatabaseTable::ROLES, static function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });
    }
};
