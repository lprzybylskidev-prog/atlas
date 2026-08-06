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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class BreakLockRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_break_renders_lock_screen_and_redirects_application_gets(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, CoreAuthorizationPermissionCatalog::DASHBOARD);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::BREAK_SHOW);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::BREAK_END);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::USER_REPORT);
        $this->createActiveBreak($user, $team);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/work-time/break')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/BreakLock')
                ->where('mfaRequired', false)
                ->where('breakSession.maximumSeconds', 14400)
                ->where('breakSession.remainingSeconds', fn (int $seconds): bool => $seconds > 0)
                ->where('breakSession.exceededSeconds', 0));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/work-time')
            ->assertRedirect(route(TimeTrackingPermissionCatalog::BREAK_SHOW));
    }

    public function test_user_can_end_active_break_after_password_confirmation(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, CoreAuthorizationPermissionCatalog::DASHBOARD);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::BREAK_SHOW);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::BREAK_END);
        $this->createActiveBreak($user, $team);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->from('/user/work-time/break')
            ->post('/user/work-time/break/end', [
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_BREAKS, [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'closure_reason' => 'normal',
            'requires_manager_review' => false,
        ]);
        self::assertSame(0, DB::table(DatabaseTable::TIME_TRACKING_BREAKS)->where('user_id', $user->id)->whereNull('ended_at')->count());
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function userWithTeam(): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);
        $team = Team::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Break Lock Team',
            'slug' => 'break-lock-team',
            'is_active' => true,
        ]);

        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'is_head_manager' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $team];
    }

    private function createActiveBreak(User $user, Team $team): void
    {
        $workSessionId = DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'break-lock-session',
            'started_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(DatabaseTable::TIME_TRACKING_BREAKS)->insert([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'started_at' => now()->subMinutes(10),
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
