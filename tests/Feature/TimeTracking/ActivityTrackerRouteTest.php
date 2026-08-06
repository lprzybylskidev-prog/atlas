<?php

declare(strict_types=1);

namespace Tests\Feature\TimeTracking;

use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
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
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ActivityTrackerRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_warning_closes_counted_work_without_reopening_session(): void
    {
        [$user, $team] = $this->userWithTeam(inactivityTimeoutMinutes: 1);
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, CoreAuthorizationPermissionCatalog::DASHBOARD);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::ACTIVITY_RECORD);
        $this->createActiveWork($user, $team);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('timeTracking.activity.enabled', true)
                ->where('timeTracking.activity.thresholdSeconds', 60)
                ->where('timeTracking.activity.warningSeconds', 30));

        $this->actingAs($user)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'atlas_session_created_at' => now()->subMinutes(2)->toIso8601String(),
                'atlas_session_last_activity_at' => now()->subSeconds(65)->toIso8601String(),
            ])
            ->postJson('/time-tracking/activity', [
                'inactive_ms' => 65000,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'ended',
                'workEnded' => true,
                'thresholdSeconds' => 60,
                'warningSeconds' => 30,
            ]);

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_WORK_SESSIONS, [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'closure_reason' => 'inactivity',
        ]);
        self::assertSame(0, DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->where('user_id', $user->id)->whereNull('ended_at')->count());
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function userWithTeam(?int $inactivityTimeoutMinutes = null): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $user = User::factory()->create();
        $team = Team::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Activity Tracker Team',
            'slug' => 'activity-tracker-team',
            'is_active' => true,
        ]);

        $assignmentId = (int) DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insertGetId([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'is_head_manager' => false,
            'inactivity_timeout_minutes' => $inactivityTimeoutMinutes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(DatabaseTable::TIME_TRACKING_USER_TEAM_SETTINGS)->insert([
            'public_id' => (string) Str::ulid(),
            'team_user_assignment_id' => $assignmentId,
            'tracking_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $team];
    }

    private function createActiveWork(User $user, Team $team): void
    {
        DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insert([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'activity-tracker-session',
            'started_at' => now()->subHour(),
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
}
