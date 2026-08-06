<?php

declare(strict_types=1);

use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(AuthorizationDatabaseTable::ROLES, static function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('name');
        });

        Schema::table(AuthorizationDatabaseTable::PERMISSIONS, static function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('name');
        });

        Schema::table(TeamsDatabaseTable::TEAMS, static function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('name');
        });

        DB::table(AuthorizationDatabaseTable::ROLES)->whereNull('display_name')->update(['display_name' => DB::raw('name')]);
        DB::table(AuthorizationDatabaseTable::PERMISSIONS)->whereNull('display_name')->update(['display_name' => DB::raw('name')]);
        DB::table(TeamsDatabaseTable::TEAMS)->whereNull('display_name')->update(['display_name' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table(TeamsDatabaseTable::TEAMS, static function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });

        Schema::table(AuthorizationDatabaseTable::PERMISSIONS, static function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });

        Schema::table(AuthorizationDatabaseTable::ROLES, static function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });
    }
};
