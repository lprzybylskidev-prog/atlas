<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Packages\ApplyOnboardingPackageToUser;
use App\Modules\Core\Authorization\Application\Packages\PackageRoleManager;
use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Permissions\TeamPermissionNames;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Core\Users\Application\Commands\CreateUserAccountCommand;
use App\Modules\Core\Users\Application\CreateUserAccount;
use App\Modules\Core\Users\Infrastructure\Notifications\FirstPasswordSetupNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class OnboardingPermissionPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_role_from_package_and_add_only_missing_permissions_to_existing_role(): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();
        $this->createOnboardingPackage(
            name: 'collections.team_leader',
            roleName: StarterRoleName::Manager->value,
            templatePermissions: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                TeamPermissionNames::MANAGERS_VIEW,
            ],
        );

        $manager = $this->app->make(PackageRoleManager::class);
        $manager->createRoleFromPackage('collections.team_leader', 'support-manager');

        $role = Role::query()->where('name', 'support-manager')->firstOrFail();

        self::assertTrue($role->hasPermissionTo(CoreAuthorizationPermissionCatalog::DASHBOARD));

        $extra = Permission::query()->where('name', CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS)->firstOrFail();
        $role->givePermissionTo($extra);
        $role->revokePermissionTo(CoreAuthorizationPermissionCatalog::DASHBOARD);

        $diff = $manager->diff('collections.team_leader', 'support-manager');

        self::assertSame([CoreAuthorizationPermissionCatalog::DASHBOARD], $diff->missingPermissionNames);
        self::assertContains(CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS, $diff->unchangedExtraPermissionNames);

        $manager->addMissingPermissionsToRole('collections.team_leader', 'support-manager');
        $role->refresh();

        self::assertTrue($role->hasPermissionTo(CoreAuthorizationPermissionCatalog::DASHBOARD));
        self::assertTrue($role->hasPermissionTo(CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS));
    }

    public function test_onboarding_package_is_a_one_time_user_creation_preset(): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();
        $this->createOnboardingPackage(
            name: 'collections.team_leader',
            roleName: StarterRoleName::Manager->value,
            templatePermissions: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                TeamPermissionNames::MANAGERS_VIEW,
            ],
        );
        $this->createOnboardingPackage(
            name: 'collections.agent',
            roleName: StarterRoleName::User->value,
            templatePermissions: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
            ],
        );

        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);

        $applier = $this->app->make(ApplyOnboardingPackageToUser::class);
        $applier->apply('collections.team_leader', $user->public_id, $team->public_id, null, duringUserCreation: true);

        self::assertDatabaseHas('user_onboarding_packages', [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'package_name' => 'collections.team_leader',
        ]);
        self::assertDatabaseHas('audit_events', [
            'action' => 'authorization.user_onboarding_package_applied',
            'target_public_id' => $user->public_id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $applier->apply('collections.agent', $user->public_id, $team->public_id, null);
    }

    public function test_user_creation_can_apply_selected_onboarding_package_once(): void
    {
        Notification::fake();
        $this->app->make(InstallStarterRoles::class)->handle();
        $this->createOnboardingPackage(
            name: 'collections.team_leader',
            roleName: StarterRoleName::Manager->value,
            templatePermissions: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                TeamPermissionNames::MANAGERS_VIEW,
            ],
        );

        $team = Team::query()->create(['name' => 'Operations']);

        $created = $this->app->make(CreateUserAccount::class)->handle(new CreateUserAccountCommand(
            name: 'Packaged User',
            email: 'packaged@example.test',
            onboardingPackageName: 'collections.team_leader',
            teamPublicId: (string) $team->public_id,
            actorPublicId: null,
        ));

        $user = User::query()->where('public_id', $created->publicId)->firstOrFail();

        self::assertDatabaseHas('user_onboarding_packages', [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'package_name' => 'collections.team_leader',
        ]);
        self::assertDatabaseHas('audit_events', [
            'action' => 'authorization.user_onboarding_package_applied',
            'target_public_id' => $created->publicId,
        ]);

        Notification::assertSentOnDemand(FirstPasswordSetupNotification::class);
    }

    /**
     * @param  list<string>  $templatePermissions
     */
    private function createOnboardingPackage(string $name, string $roleName, array $templatePermissions): void
    {
        $this->app->make(OnboardingPackageStore::class)->upsert(
            name: $name,
            label: $name,
            initialRoleNames: [$roleName],
            directPermissionNames: [],
            templatePermissionNames: $templatePermissions,
        );
    }
}
