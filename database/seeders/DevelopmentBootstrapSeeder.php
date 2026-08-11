<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Identity\Application\Public\Contracts\VerifiedUserFixtureBuilder;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use Illuminate\Database\Seeder;

class DevelopmentBootstrapSeeder extends Seeder
{
    public const PREVIEW_EMAIL = 'admin@example.test';

    public const PREVIEW_PASSWORD = 'password';

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $team = app(BootstrapTeamProvider::class)->provide(SystemBootstrapSeeder::ADMINISTRATION_TEAM_NAME);
        $admin = app(VerifiedUserFixtureBuilder::class)->provide(
            name: 'Admin',
            email: self::PREVIEW_EMAIL,
            plainPassword: self::PREVIEW_PASSWORD,
            accountSensitivity: 'sensitive',
        );

        app(AdministratorAccessManager::class)->assignAdministrator(
            userPublicId: $admin->publicId,
            teamPublicId: $team->publicId,
        );
    }
}
