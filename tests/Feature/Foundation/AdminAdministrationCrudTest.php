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
use Inertia\Testing\AssertableInertia;
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

    public function test_admin_tables_use_validated_server_state_and_saved_team_views_are_audited(): void
    {
        $actor = User::factory()->create(['name' => 'Zeta Owner', 'email' => 'owner@example.test']);
        $matchingUser = User::factory()->create(['name' => 'Alpha Match', 'email' => 'alpha@example.test']);
        User::factory()->create(['name' => 'Beta Other', 'email' => 'beta@example.test']);
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $session = $this->adminSession($activeTeam);

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/users?search=alpha&sort=not_allowed&direction=desc&per_page=25&page=1&columns=name,email,unknown')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->where('table.key', 'admin.users')
                ->where('table.state.sort', 'name')
                ->where('table.state.direction', 'desc')
                ->where('table.state.perPage', 25)
                ->where('table.state.columns', ['name', 'email'])
                ->where('table.pagination.total', 1)
                ->where('users.0.publicId', $matchingUser->public_id)
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/users?columns=')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->where('table.state.columns', [])
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/table-views', [
                'table_key' => 'admin.users',
                'name' => 'Team active users',
                'type' => 'team',
                'state' => [
                    'sort' => 'email',
                    'direction' => 'asc',
                    'search' => 'alpha',
                    'columns' => ['name', 'email', 'secret'],
                    'columnOrder' => ['email', 'name'],
                ],
            ])
            ->assertRedirect();

        self::assertDatabaseHas('table_saved_views', [
            'table_key' => 'admin.users',
            'name' => 'Team active users',
            'type' => 'team',
            'team_id' => $activeTeam->id,
        ]);
        self::assertDatabaseHas('security_audit_events', [
            'module' => 'shared',
            'action' => 'table_saved_view.created',
            'result' => 'success',
            'actor_public_id' => $actor->public_id,
        ]);

        $view = DB::table('table_saved_views')->where('name', 'Team active users')->first();
        self::assertIsObject($view);
        self::assertSame(['name', 'email'], $this->jsonStringList($view, 'state', 'columns'));

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/table-views', [
                'table_key' => 'admin.users',
                'name' => 'No visible data columns',
                'type' => 'private',
                'state' => [
                    'sort' => 'name',
                    'direction' => 'asc',
                    'columns' => [],
                    'columnOrder' => [],
                ],
            ])
            ->assertRedirect();

        $emptyColumnsView = DB::table('table_saved_views')->where('name', 'No visible data columns')->first();
        self::assertIsObject($emptyColumnsView);
        self::assertSame([], $this->jsonStringList($emptyColumnsView, 'state', 'columns'));
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
    private function jsonStringList(object $record, string $property, ?string $nestedProperty = null): array
    {
        $values = get_object_vars($record);
        $decoded = json_decode(is_string($values[$property] ?? null) ? $values[$property] : '[]', true);

        if ($nestedProperty !== null && is_array($decoded)) {
            $decoded = $decoded[$nestedProperty] ?? [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_string'));
    }
}
