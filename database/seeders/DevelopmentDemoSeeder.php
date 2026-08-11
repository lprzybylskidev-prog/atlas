<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Optional\TimeTracking\Application\Contracts\TimeTrackingFixtureBuilder;
use Illuminate\Database\Seeder;

final class DevelopmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $fixtures = app(TimeTrackingFixtureBuilder::class);

        if ($fixtures->available()) {
            $fixtures->seed();
        }
    }
}
