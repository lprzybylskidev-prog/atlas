<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Packages\ApplyOnboardingPackageToUser;
use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Modules\Core\Teams\Application\Public\Permissions\TeamPermissionNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevelopmentDemoSeeder extends Seeder
{
    public const PREVIEW_EMAIL = 'admin@example.test';

    public const PREVIEW_PASSWORD = 'password';

    public function run(): void
    {
        app(InstallStarterRoles::class)->handle();
        $this->upsertOnboardingPackages();

        $teams = [
            app(BootstrapTeamProvider::class)->provide('Collections North'),
            app(BootstrapTeamProvider::class)->provide('Collections South'),
            app(BootstrapTeamProvider::class)->provide('Back Office'),
        ];

        $admin = User::query()->firstOrNew([
            'email' => self::PREVIEW_EMAIL,
        ]);

        $admin->forceFill([
            'name' => 'Admin',
            'password' => Hash::make(self::PREVIEW_PASSWORD),
            'email_verified_at' => now(),
            'first_password_set_at' => now(),
        ])->save();

        app(AdministratorAccessManager::class)->assignAdministrator(
            userPublicId: (string) $admin->public_id,
            teamPublicId: $teams[0]->publicId,
        );

        $packages = [
            'collections.agent',
            'collections.team_leader',
            'back_office.specialist',
        ];

        foreach (range(1, 9) as $index) {
            $team = $teams[($index - 1) % count($teams)];
            $package = $packages[($index - 1) % count($packages)];
            $user = User::query()->firstOrNew([
                'email' => sprintf('demo.user.%02d@example.test', $index),
            ]);

            $user->forceFill([
                'name' => fake()->name(),
                'password' => Hash::make(self::PREVIEW_PASSWORD),
                'email_verified_at' => now(),
                'first_password_set_at' => now(),
            ])->save();

            if (! DB::table('user_onboarding_packages')->where('user_id', $user->id)->exists()) {
                app(ApplyOnboardingPackageToUser::class)->apply(
                    packageName: $package,
                    userPublicId: (string) $user->public_id,
                    teamPublicId: $team->publicId,
                    actorPublicId: (string) $admin->public_id,
                    duringUserCreation: true,
                );
            }
        }
    }

    private function upsertOnboardingPackages(): void
    {
        $packages = app(OnboardingPackageStore::class);

        $packages->upsert(
            name: 'collections.agent',
            label: 'Collections agent',
            initialRoleNames: [StarterRoleName::User->value],
            directPermissionNames: [],
            templatePermissionNames: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
            ],
        );

        $packages->upsert(
            name: 'collections.team_leader',
            label: 'Collections team leader',
            initialRoleNames: [StarterRoleName::Manager->value],
            directPermissionNames: [],
            templatePermissionNames: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                TeamPermissionNames::MANAGERS_VIEW,
            ],
        );

        $packages->upsert(
            name: 'back_office.specialist',
            label: 'Back office specialist',
            initialRoleNames: [StarterRoleName::User->value],
            directPermissionNames: [
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS,
            ],
            templatePermissionNames: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS,
            ],
        );
    }
}
