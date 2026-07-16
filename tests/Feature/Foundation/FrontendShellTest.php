<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class FrontendShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_as_inertia_auth_layout_entry(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Auth/Login')
                    ->where('locale', 'pl')
                    ->where('preferences.theme', 'light')
                    ->where('auth.user', null),
            );
    }

    public function test_application_and_admin_previews_require_authentication(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_open_application_and_admin_previews(): void
    {
        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $presetPublicId = '01K00000000000000000000000';

        $this->assignStarterRoleInTeam($user, $team, StarterRoleName::Administrator->value);
        DB::table(DatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)->insert([
            'public_id' => $presetPublicId,
            'team_id' => $team->id,
            'name' => 'operations.agent',
            'label' => 'Operations agent',
            'initial_role_names' => json_encode([StarterRoleName::WorkspaceAccess->value], JSON_THROW_ON_ERROR),
            'direct_permission_names' => json_encode([], JSON_THROW_ON_ERROR),
            'template_permission_names' => json_encode([], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminSession = [
            'active_team_public_id' => $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
        ];

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('navigation.breadcrumbs', 1)
                ->where('navigation.breadcrumbs.0.label', 'Pulpit')
                ->where('navigation.breadcrumbs.0.url', 'http://localhost:8000'));

        $this->actingAs($user)
            ->withSession($adminSession)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/SystemStatus')
                ->has('navigation.breadcrumbs', 2)
                ->where('navigation.breadcrumbs.0.label', 'Admin')
                ->where('navigation.breadcrumbs.0.url', null)
                ->where('navigation.breadcrumbs.1.label', 'Dashboard')
                ->where('navigation.breadcrumbs.1.url', null));

        $this->actingAs($user)
            ->withSession($adminSession)
            ->get('/admin/teams')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Index')
                ->has('teams'));

        $this->actingAs($user)
            ->withSession($adminSession)
            ->get('/admin/teams/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Create'));

        $this->actingAs($user)
            ->withSession($adminSession)
            ->get('/admin/teams/'.$team->public_id.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Edit')
                ->where('team.publicId', (string) $team->public_id));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/admin/users/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Create')
                ->has('packages')
                ->has('copySources'));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/admin/authorization/roles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Roles')
                ->has('roles'));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/admin/authorization/roles/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Roles/Create'));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/admin/authorization/roles/'.StarterRoleName::Administrator->value.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Roles/Edit')
                ->where('role.name', StarterRoleName::Administrator->value));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/admin/authorization/packages')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Packages')
                ->has('packages'));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/admin/authorization/packages/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Packages/Create')
                ->has('roleOptions')
                ->has('permissionOptions'));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/admin/authorization/packages/'.$presetPublicId.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Packages/Edit')
                ->where('package.name', 'operations.agent')
                ->has('roleOptions')
                ->has('permissionOptions'));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/admin/authorization/permissions')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Permissions')
                ->has('permissions'));
    }

    public function test_stale_active_team_session_is_replaced_with_first_assigned_team(): void
    {
        $user = User::factory()->create();
        $assignedTeam = Team::query()->create(['name' => 'Assigned Operations']);
        $staleTeam = Team::query()->create(['name' => 'Old Session Team']);

        $this->assignStarterRoleInTeam($user, $assignedTeam, StarterRoleName::Administrator->value);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $staleTeam->public_id])
            ->get('/')
            ->assertOk()
            ->assertSessionHas('active_team_public_id', $assignedTeam->public_id);
    }

    private function assignStarterRoleInTeam(User $user, Team $team, string $roleName): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $role = Role::query()->where('name', $roleName)->firstOrFail();

        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(DatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }
}
