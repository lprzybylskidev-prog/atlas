<?php

declare(strict_types=1);

namespace Tests\Feature\TimeTracking;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class AdminClosedPeriodCorrectionRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_closed_period_override_when_no_head_manager_exists(): void
    {
        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_CLOSED_PERIOD_OVERRIDE);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->from('/admin')
            ->post(route(TimeTrackingPermissionCatalog::ADMIN_CLOSED_PERIOD_OVERRIDE), $this->payload($target, $team))
            ->assertRedirect('/admin')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS, [
            'user_id' => $target->id,
            'team_id' => $team->id,
            'request_type' => 'closed_period_override',
            'status' => 'corrected',
            'decided_by_user_id' => $admin->id,
        ]);
        $requestId = DB::table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)->where('user_id', $target->id)->value('id');

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_CLOSED_PERIOD_OVERRIDES, [
            'correction_request_id' => $requestId,
            'actor_user_id' => $admin->id,
            'actor_scope' => 'admin',
            'admin_mode_confirmed' => true,
            'high_risk_reauthenticated' => true,
            'mfa_confirmed' => true,
            'before_after_preview_confirmed' => true,
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'time_tracking',
            'action' => 'time_tracking.closed_period_override_created',
            'result' => 'succeeded',
            'actor_public_id' => $admin->public_id,
            'target_public_id' => $target->public_id,
            'team_public_id' => $team->public_id,
            'is_security' => true,
        ]);
    }

    public function test_admin_closed_period_override_is_rejected_when_head_manager_exists(): void
    {
        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $headManager = User::factory()->create();
        $this->assignUserToTeam($headManager, $team, headManager: true);
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_CLOSED_PERIOD_OVERRIDE);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->from('/admin')
            ->post(route(TimeTrackingPermissionCatalog::ADMIN_CLOSED_PERIOD_OVERRIDE), $this->payload($target, $team))
            ->assertRedirect('/admin')
            ->assertSessionHasErrors(['team_public_id']);

        $this->assertDatabaseMissing(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS, [
            'request_type' => 'closed_period_override',
            'user_id' => $target->id,
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'time_tracking',
            'action' => 'time_tracking.closed_period_override_rejected',
            'result' => 'rejected',
            'actor_public_id' => $admin->public_id,
            'target_public_id' => $target->public_id,
            'team_public_id' => $team->public_id,
            'is_security' => true,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Team}
     */
    private function adminTargetAndTeam(): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $admin = User::factory()->create();
        $target = User::factory()->create();
        $team = Team::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Time Tracking Admin Team',
            'slug' => 'time-tracking-admin-team',
            'is_active' => true,
        ]);

        $this->assignUserToTeam($admin, $team);
        $this->assignUserToTeam($target, $team);

        return [$admin, $target, $team];
    }

    private function assignUserToTeam(User $user, Team $team, bool $headManager = false): void
    {
        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'is_head_manager' => $headManager,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignDirectPermissionInTeam(User $user, Team $team, string $permissionName): void
    {
        $permission = Permission::query()->where('name', $permissionName)->firstOrFail();

        DB::table(DatabaseTable::MODEL_HAS_PERMISSIONS)->insert([
            'permission_id' => $permission->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    private function activateTimeTracking(Team $team): void
    {
        $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
            moduleKey: 'time_tracking',
            scope: ModuleActivationScope::Global,
            enabled: true,
            reason: 'Feature test setup',
            source: ModuleActivationSource::Manual,
        ));
        $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
            moduleKey: 'time_tracking',
            scope: ModuleActivationScope::Team,
            enabled: true,
            reason: 'Feature test setup',
            teamId: $team->id,
            source: ModuleActivationSource::Manual,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function adminSession(Team $team): array
    {
        return [
            'active_team_public_id' => $team->public_id,
            AdministrativeSessionManager::ENTERED_AT => now()->toIso8601String(),
            AdministrativeSessionManager::LAST_ACTIVITY_AT => now()->toIso8601String(),
            AdministrativeSessionManager::HIGH_RISK_CONFIRMED_AT => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $target, Team $team): array
    {
        return [
            'user_public_id' => $target->public_id,
            'team_public_id' => $team->public_id,
            'original_started_at' => '2026-07-10T08:00:00+02:00',
            'original_ended_at' => '2026-07-10T09:00:00+02:00',
            'original_exact_seconds' => 3600,
            'final_started_at' => '2026-07-10T08:00:00+02:00',
            'final_ended_at' => '2026-07-10T09:30:00+02:00',
            'final_exact_seconds' => 5400,
            'before_after_preview_confirmed' => '1',
            'reason' => 'No eligible head manager exists for this closed period correction.',
        ];
    }
}
