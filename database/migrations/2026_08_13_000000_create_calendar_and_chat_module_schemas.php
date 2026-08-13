<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::CORE_CALENDAR);
        DatabaseSchema::ensure(DatabaseSchema::OPTIONAL_CHAT);
    }

    public function down(): void
    {
        DB::statement('drop schema if exists '.DatabaseSchema::OPTIONAL_CHAT.' cascade');
        DB::statement('drop schema if exists '.DatabaseSchema::CORE_CALENDAR.' cascade');
    }
};
