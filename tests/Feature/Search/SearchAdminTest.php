<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\Search\Application\SearchRebuildProcess;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class SearchAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_search_status_and_start_rebuild(): void
    {
        Config::set('scout.meilisearch.host', '');
        [$admin, $team] = $this->adminWithTeam();
        $this->activateModules($team, ['managed_processes', 'search']);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/search')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Search/Index')
                ->where('auth.availableAdminRoutes', fn (Collection $routes): bool => $routes->contains('admin.search.index'))
                ->where('summary.indexes', 0)
                ->where('readiness.status', 'degraded')
                ->where('rebuildConfirmation', 'REBUILD SEARCH'));

        $response = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/search/rebuild', [
                'confirmation' => 'REBUILD SEARCH',
            ])
            ->assertRedirect();

        $runPublicId = basename((string) $response->headers->get('Location'));

        $this->assertDatabaseHas(DatabaseTable::MANAGED_PROCESS_RUNS, [
            'public_id' => $runPublicId,
            'process_key' => SearchRebuildProcess::KEY,
            'module_key' => 'search',
        ]);
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function adminWithTeam(): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $admin = User::factory()->create();
        $team = Team::query()->create([
            'public_id' => '01J00000000000000000000042',
            'name' => 'Operations',
            'slug' => 'operations-search',
            'is_active' => true,
        ]);
        $role = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();

        $this->app['db']->table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->app['db']->table(DatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $admin->id,
            'team_id' => $team->id,
        ]);

        return [$admin, $team];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminSession(Team $team): array
    {
        return [
            'active_team_public_id' => $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
            'atlas_admin_mode_entered_at' => now()->toIso8601String(),
            'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>  $moduleKeys
     */
    private function activateModules(Team $team, array $moduleKeys): void
    {
        foreach ($moduleKeys as $moduleKey) {
            $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
                moduleKey: $moduleKey,
                scope: ModuleActivationScope::Global,
                enabled: true,
                reason: 'Feature test setup',
                source: ModuleActivationSource::Manual,
            ));
            $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
                moduleKey: $moduleKey,
                scope: ModuleActivationScope::Team,
                enabled: true,
                reason: 'Feature test setup',
                teamId: $team->id,
                source: ModuleActivationSource::Manual,
            ));
        }
    }
}
