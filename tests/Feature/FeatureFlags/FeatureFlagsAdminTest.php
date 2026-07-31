<?php

declare(strict_types=1);

namespace Tests\Feature\FeatureFlags;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagKey;
use App\Modules\Optional\FeatureFlags\Application\Public\Contracts\FeatureFlagEvaluator;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class FeatureFlagsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_feature_flags_status(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $this->activateFeatureFlags($team);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/feature-flags')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/FeatureFlags/Index')
                ->where('auth.availableAdminRoutes', fn (Collection $routes): bool => $routes->contains('admin.feature-flags.index'))
                ->where('summary.registered', 3)
                ->where('summary.visible', 3)
                ->where('summary.historyRows', 0)
                ->has('flags', 3)
                ->where('flags.0.key', FeatureFlagKey::PrivacyWorkflowPreview->value)
                ->where('flags.0.effectiveEnabled', false)
                ->where('table.key', 'admin.feature-flags.flags')
                ->where('table.state.filters.team', (string) $team->public_id)
                ->where('table.state.filters.status', 'all')
                ->where('table.state.filters.source', 'all')
                ->where('table.exports.endpoint', route('admin.exports.data-table')));

    }

    public function test_team_override_changes_are_persisted_and_clear_back_to_global(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $this->activateFeatureFlags($team);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->patch('/admin/feature-flags/reports.preview/global', [
                'enabled' => true,
                'reason' => 'Enable controlled reports preview globally',
                'team_public_id' => $team->public_id,
            ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->patch('/admin/feature-flags/reports.preview/teams', [
                'team_public_id' => $team->public_id,
                'enabled' => false,
                'reason' => 'Hold the active operations team back',
            ])
            ->assertRedirect(route('admin.feature-flags.index', ['team' => $team->public_id]));

        self::assertFalse($this->app->make(FeatureFlagEvaluator::class)->enabled('reports.preview', $team->public_id));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->delete('/admin/feature-flags/reports.preview/teams', [
                'team_public_id' => $team->public_id,
                'reason' => 'Return to global rollout',
            ])
            ->assertRedirect(route('admin.feature-flags.index', ['team' => $team->public_id]));

        self::assertTrue($this->app->make(FeatureFlagEvaluator::class)->enabled('reports.preview', $team->public_id));

        $this->assertDatabaseHas(DatabaseTable::FEATURE_FLAG_HISTORY, [
            'flag_key' => 'reports.preview',
            'scope' => 'global',
            'action' => 'feature_flag.global_updated',
        ]);
        $this->assertDatabaseHas(DatabaseTable::FEATURE_FLAG_HISTORY, [
            'flag_key' => 'reports.preview',
            'scope' => 'team',
            'action' => 'feature_flag.team_updated',
        ]);
        $this->assertDatabaseHas(DatabaseTable::FEATURE_FLAG_HISTORY, [
            'flag_key' => 'reports.preview',
            'scope' => 'team',
            'action' => 'feature_flag.team_cleared',
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'feature_flags',
            'action' => 'feature_flag.team_cleared',
            'target_public_id' => 'reports.preview',
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
            'public_id' => '01J00000000000000000000052',
            'name' => 'Operations',
            'slug' => 'operations-feature-flags',
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
        ];
    }

    private function activateFeatureFlags(Team $team): void
    {
        $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
            moduleKey: 'feature_flags',
            scope: ModuleActivationScope::Global,
            enabled: true,
            reason: 'Feature test setup',
            source: ModuleActivationSource::Manual,
        ));
        $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
            moduleKey: 'feature_flags',
            scope: ModuleActivationScope::Team,
            enabled: true,
            reason: 'Feature test setup',
            teamId: $team->id,
            source: ModuleActivationSource::Manual,
        ));
    }
}
