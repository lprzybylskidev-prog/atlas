<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\Integrations\Application\Contracts\IntegrationAdapter;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationDefinition;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationTestResult;
use App\Modules\Optional\Integrations\Application\Public\Persistence\IntegrationsDatabaseTable;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class IntegrationsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_integration_status_and_test_connection(): void
    {
        Config::set('atlas.integrations.adapters', [AdminFakeIntegrationAdapter::class]);

        [$admin, $team] = $this->adminWithTeam();
        $this->activateIntegrations($team);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/integrations')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Integrations/Index')
                ->where('auth.availableAdminRoutes', function (Collection $routes): bool {
                    return $routes->contains('admin.integrations.index');
                })
                ->where('integrations.0.key', 'crm')
                ->where('integrations.0.externalApiEnabled', false)
                ->where('externalApiEnabled', false)
                ->where('summary.registered', 1)
                ->where('summary.visible', 1)
                ->where('table.key', 'admin.integrations.adapters')
                ->where('table.state.filters.status', 'all')
                ->where('table.state.filters.circuit', 'all')
                ->where('table.exports.endpoint', route('admin.exports.data-table')));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/integrations/crm/test')
            ->assertRedirect(route('admin.integrations.index'));

        $this->assertDatabaseHas(IntegrationsDatabaseTable::CONNECTIONS, [
            'integration_key' => 'crm',
            'name' => 'CRM',
            'external_api_enabled' => false,
            'last_error_message' => null,
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
            'public_id' => '01J00000000000000000000002',
            'name' => 'Operations',
            'slug' => 'operations',
            'is_active' => true,
        ]);

        $role = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();

        $this->app['db']->table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->insert([
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

    private function activateIntegrations(Team $team): void
    {
        $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
            moduleKey: 'integrations',
            scope: ModuleActivationScope::Global,
            enabled: true,
            reason: 'Feature test setup',
            source: ModuleActivationSource::Manual,
        ));
        $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
            moduleKey: 'integrations',
            scope: ModuleActivationScope::Team,
            enabled: true,
            reason: 'Feature test setup',
            teamId: $team->id,
            source: ModuleActivationSource::Manual,
        ));
    }
}

final class AdminFakeIntegrationAdapter implements IntegrationAdapter
{
    public function definition(): IntegrationDefinition
    {
        return new IntegrationDefinition(
            key: 'crm',
            name: 'CRM',
            adapterClass: self::class,
            sourceOfTruth: 'Atlas owns cases; CRM owns lead intake.',
            providedScopes: ['leads.read'],
        );
    }

    public function testConnection(string $correlationId): IntegrationTestResult
    {
        return new IntegrationTestResult(
            integrationKey: 'crm',
            successful: true,
            message: 'CRM connection is healthy.',
            testedAt: CarbonImmutable::now('UTC'),
            metadata: ['correlation_id' => $correlationId],
        );
    }
}
