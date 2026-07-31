<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Contracts\Support\Arrayable;
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

        $this->assignStarterRoleInTeam($user, $team, StarterRoleName::Administrator->value);

        $adminSession = [
            'active_team_public_id' => $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
            'atlas_admin_mode_entered_at' => now()->toIso8601String(),
            'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
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
                ->where('navigation.breadcrumbs.1.url', null)
                ->where('dashboard.release.environment', 'testing')
                ->where('dashboard.externalMechanisms.items.0.key', 'postgresql')
                ->where('dashboard.externalMechanisms.items.1.key', 'redis')
                ->where('dashboard.externalMechanisms.items.2.key', 'storage')
                ->where('dashboard.modules.failedActivationSchedules', 0)
                ->where('dashboard.modules.scheduledActivationChanges', 0)
                ->has('dashboard.modules.items')
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.users.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.teams.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.managers.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.audit.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.audit.security-history.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.files.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.privacy-retention.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.privacy-retention.legal-holds.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.privacy-retention.legal-holds.create'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.privacy-retention.operations.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.logs.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.rate-limits.index'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.pulse.view'))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => ! self::stringListContains($routes, 'admin.telescope.view'))
                ->where('availability.0.elementKey', 'admin.system-status.release')
                ->where('availability.1.elementKey', 'admin.system-status.readiness')
                ->where('availability.2.elementKey', 'admin.system-status.modules'));
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

    private static function stringListContains(mixed $values, string $value): bool
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        if ($values instanceof \Traversable) {
            $values = iterator_to_array($values);
        }

        if (! is_array($values)) {
            return false;
        }

        foreach ($values as $key => $item) {
            if ($key === $value || $item === $value) {
                return true;
            }
        }

        return false;
    }
}
