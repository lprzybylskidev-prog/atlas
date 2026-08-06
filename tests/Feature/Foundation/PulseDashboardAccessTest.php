<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class PulseDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pulse_uses_non_serializing_dashboard_cache_by_default(): void
    {
        self::assertSame('array', config('pulse.cache'));
        self::assertFalse(config('cache.serializable_classes'));
    }

    public function test_admin_can_view_pulse_dashboard(): void
    {
        [$admin, $team] = $this->userWithTeamAndRole(StarterRoleName::Administrator);

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/pulse')
            ->assertOk();
    }

    public function test_non_admin_cannot_view_pulse_dashboard(): void
    {
        [$user, $team] = $this->userWithTeamAndRole(StarterRoleName::WorkspaceAccess);

        $this->actingAs($user)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/pulse')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function userWithTeamAndRole(StarterRoleName $roleName): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $role = Role::query()->where('name', $roleName->value)->firstOrFail();

        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);

        return [$user, $team];
    }
}
