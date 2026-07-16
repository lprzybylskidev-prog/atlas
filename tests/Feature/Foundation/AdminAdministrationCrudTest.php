<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminAdministrationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_requires_password_confirmation(): void
    {
        $actor = User::factory()->create();
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $session = ['active_team_public_id' => $activeTeam->public_id];

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin')
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($actor)
            ->withSession([
                ...$session,
                'auth.password_confirmed_at' => now()->unix(),
            ])
            ->get('/admin')
            ->assertOk();
    }

    public function test_admin_can_update_team_role_and_onboarding_preset_from_separate_edit_routes(): void
    {
        $actor = User::factory()->create();
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $editableTeam = Team::query()->create(['name' => 'Legacy Support']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        Role::query()->create([
            'name' => 'operations.viewer',
            'guard_name' => 'web',
            config()->string('permission.column_names.team_foreign_key') => null,
        ]);

        $this->app->make(OnboardingPackageStore::class)->upsert(
            name: 'collections.agent',
            label: 'Collections agent',
            initialRoleNames: [StarterRoleName::User->value],
            directPermissionNames: ['dashboard'],
            templatePermissionNames: ['dashboard'],
        );

        $session = $this->adminSession($activeTeam);

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/teams/'.$editableTeam->public_id, ['name' => 'Client Support'])
            ->assertRedirect(route('admin.teams.edit', ['team' => $editableTeam->public_id]));

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/authorization/roles/operations.viewer', ['name' => 'operations.reader'])
            ->assertRedirect(route('admin.authorization.roles.edit', ['role' => 'operations.reader']));

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/authorization/roles/operations.reader', [
                'name' => 'operations.reader',
                'permissions' => [CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS],
            ])
            ->assertRedirect(route('admin.authorization.roles.edit', ['role' => 'operations.reader']));

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/authorization/packages/collections.agent', [
                'label' => 'Collections specialist',
                'initial_roles' => [StarterRoleName::Manager->value],
                'direct_permissions' => ['dashboard', 'admin.users.index'],
            ])
            ->assertRedirect(route('admin.authorization.packages.edit', ['package' => 'collections.agent']));

        self::assertDatabaseHas('teams', [
            'public_id' => $editableTeam->public_id,
            'name' => 'Client Support',
        ]);
        self::assertDatabaseHas('roles', [
            'name' => 'operations.reader',
            'guard_name' => 'web',
        ]);
        self::assertDatabaseMissing('roles', [
            'name' => 'operations.viewer',
            'guard_name' => 'web',
        ]);
        self::assertDatabaseHas('authorization_onboarding_packages', [
            'name' => 'collections.agent',
            'label' => 'Collections specialist',
        ]);

        $package = DB::table('authorization_onboarding_packages')->where('name', 'collections.agent')->first();
        $role = Role::query()->where('name', 'operations.reader')->firstOrFail();
        $permission = DB::table('permissions')
            ->where('name', CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS)
            ->first(['id']);

        self::assertIsObject($package);
        self::assertIsObject($permission);
        self::assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => get_object_vars($permission)['id'] ?? null,
        ]);
        self::assertSame([StarterRoleName::Manager->value], $this->jsonStringList($package, 'initial_role_names'));
        self::assertSame(['dashboard', 'admin.users.index'], $this->jsonStringList($package, 'direct_permission_names'));
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

    /**
     * @return list<string>
     */
    private function jsonStringList(object $record, string $property): array
    {
        $values = get_object_vars($record);
        $decoded = json_decode(is_string($values[$property] ?? null) ? $values[$property] : '[]', true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_string'));
    }
}
