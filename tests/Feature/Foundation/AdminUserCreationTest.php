<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Notifications\UserEmailVerificationNotification;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Core\Users\Infrastructure\Notifications\FirstPasswordSetupNotification;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminUserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_creation_route_uses_normal_use_case_and_onboarding_package(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);
        $this->createOnboardingPackage('collections.agent', StarterRoleName::User->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/users', [
                'name' => 'Created In Admin',
                'email' => 'created.admin@example.test',
                'onboarding_package' => 'collections.agent',
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'created.admin@example.test')->firstOrFail();

        self::assertDatabaseHas('user_onboarding_packages', [
            'user_id' => $created->id,
            'team_id' => $team->id,
            'package_name' => 'collections.agent',
        ]);
        Notification::assertSentOnDemand(FirstPasswordSetupNotification::class);
    }

    public function test_admin_user_creation_can_copy_authorization_from_existing_user(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $source = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);
        $this->assignStarterRoleInTeam($source, $team, StarterRoleName::Manager->value);
        $this->assignDirectPermissionInTeam($source, $team, 'admin.authorization.permissions.index');

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/users', [
                'name' => 'Copied Permissions',
                'email' => 'copied.permissions@example.test',
                'authorization_mode' => 'copy',
                'copy_authorization_from_user' => $source->public_id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'copied.permissions@example.test')->firstOrFail();
        $managerRole = Role::query()->where('name', StarterRoleName::Manager->value)->firstOrFail();
        $permission = Permission::query()->where('name', 'admin.authorization.permissions.index')->firstOrFail();

        self::assertDatabaseHas('team_user_assignments', [
            'team_id' => $team->id,
            'user_id' => $created->id,
        ]);
        self::assertDatabaseHas('model_has_roles', [
            'role_id' => $managerRole->id,
            'model_id' => $created->id,
            'team_id' => $team->id,
        ]);
        self::assertDatabaseHas('model_has_permissions', [
            'permission_id' => $permission->id,
            'model_id' => $created->id,
            'team_id' => $team->id,
        ]);
        self::assertDatabaseMissing('user_onboarding_packages', [
            'user_id' => $created->id,
        ]);
        Notification::assertSentOnDemand(FirstPasswordSetupNotification::class);
    }

    public function test_admin_can_create_onboarding_package_from_admin_panel(): void
    {
        $actor = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/authorization/packages', [
                'name' => 'legal.assistant',
                'label' => 'Legal assistant',
                'initial_roles' => [StarterRoleName::User->value],
                'direct_permissions' => ['dashboard'],
            ])
            ->assertRedirect(route('admin.authorization.packages.index'));

        self::assertDatabaseHas('authorization_onboarding_packages', [
            'name' => 'legal.assistant',
            'label' => 'Legal assistant',
        ]);
    }

    public function test_admin_can_require_user_email_verification_again_without_resetting_password(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $target = User::factory()->create([
            'email_verified_at' => now(),
            'first_password_set_at' => now(),
        ]);
        $firstPasswordSetAt = $this->timestampOf($target->first_password_set_at);
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/users/'.$target->public_id.'/require-email-verification')
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();

        self::assertNull($target->email_verified_at);
        self::assertSame($firstPasswordSetAt, $this->timestampOf($target->first_password_set_at));
        Notification::assertSentTo($target, UserEmailVerificationNotification::class);
        Notification::assertNotSentTo($target, FirstPasswordSetupNotification::class);
    }

    private function timestampOf(mixed $value): int
    {
        self::assertInstanceOf(DateTimeInterface::class, $value);

        return $value->getTimestamp();
    }

    private function assignStarterRoleInTeam(User $user, Team $team, string $roleName): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $role = Role::query()->where('name', $roleName)->firstOrFail();

        DB::table('team_user_assignments')->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function adminSession(Team $team): array
    {
        return [
            'active_team_public_id' => $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
        ];
    }

    private function assignDirectPermissionInTeam(User $user, Team $team, string $permissionName): void
    {
        $permission = Permission::query()->where('name', $permissionName)->firstOrFail();

        DB::table('model_has_permissions')->insert([
            'permission_id' => $permission->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    private function createOnboardingPackage(string $name, string $roleName): void
    {
        $this->app->make(OnboardingPackageStore::class)->upsert(
            name: $name,
            label: $name,
            initialRoleNames: [$roleName],
            directPermissionNames: [],
            templatePermissionNames: [CoreAuthorizationPermissionCatalog::DASHBOARD],
        );
    }
}
