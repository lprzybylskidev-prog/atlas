<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentDemoSeeder extends Seeder
{
    public const PREVIEW_EMAIL = 'admin@example.test';

    public const PREVIEW_PASSWORD = 'password';

    public const ADMIN_TEAM_NAME = 'Administration';

    public function run(): void
    {
        $team = app(BootstrapTeamProvider::class)->provide(self::ADMIN_TEAM_NAME);
        $admin = User::query()->firstOrNew([
            'email' => self::PREVIEW_EMAIL,
        ]);

        $admin->forceFill([
            'name' => 'Admin',
            'password' => Hash::make(self::PREVIEW_PASSWORD),
            'email_verified_at' => now(),
            'first_password_set_at' => now(),
            'is_active' => true,
            'deactivated_at' => null,
        ])->save();

        app(AdministratorAccessManager::class)->assignAdministrator(
            userPublicId: (string) $admin->public_id,
            teamPublicId: $team->publicId,
        );
    }
}
