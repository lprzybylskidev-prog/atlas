<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Identity\Application\Public\Contracts\VerifiedUserFixtureBuilder;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Modules\Optional\TimeTracking\Application\Contracts\TimeTrackingFixtureBuilder;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipProvisioner;
use Illuminate\Database\Seeder;

final class E2eTimeTrackingSeeder extends Seeder
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

        $team = app(BootstrapTeamProvider::class)->provide('TT Demo Team North');
        $admin = app(VerifiedUserFixtureBuilder::class)->provide(
            'Visibility Admin',
            E2eVisibilitySeeder::ADMIN_EMAIL,
            E2eVisibilitySeeder::PASSWORD,
            'sensitive',
        );

        app(UserTeamMembershipProvisioner::class)->ensureUserTeamMembership($admin->publicId, $team->publicId);
        app(AdministratorAccessManager::class)->assignAdministrator($admin->publicId, $team->publicId);
    }
}
