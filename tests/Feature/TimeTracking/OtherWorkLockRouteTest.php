<?php

declare(strict_types=1);

namespace Tests\Feature\TimeTracking;

use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class OtherWorkLockRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_other_work_renders_lock_screen_and_redirects_application_gets(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, CoreAuthorizationPermissionCatalog::DASHBOARD);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::OTHER_WORK_SHOW);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::OTHER_WORK_END);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::USER_REPORT);
        $this->createActiveOtherWork($user, $team);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/work-time/other-work')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/OtherWorkLock')
                ->where('mfaRequired', false)
                ->has('otherWorkSession.elapsedSeconds')
                ->where('otherWorkSession.categoryLabel', 'Telefon do sądu')
                ->where('otherWorkSession.description', 'Calling the court clerk.')
                ->where('otherWorkSession.approvalStatus', 'pending'));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/work-time')
            ->assertRedirect(route(TimeTrackingPermissionCatalog::OTHER_WORK_SHOW));
    }

    public function test_user_can_end_active_other_work_after_password_confirmation(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, CoreAuthorizationPermissionCatalog::DASHBOARD);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::OTHER_WORK_SHOW);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::OTHER_WORK_END);
        $this->createActiveOtherWork($user, $team);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->from('/user/work-time/other-work')
            ->post('/user/work-time/other-work/end', [
                'end_note' => 'Returned with the confirmation number.',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::OTHER_WORK, [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'closure_reason' => 'normal',
            'end_note' => 'Returned with the confirmation number.',
            'requires_manager_review' => true,
        ]);
        self::assertSame(0, DB::table(TimeTrackingDatabaseTable::OTHER_WORK)->where('user_id', $user->id)->whereNull('ended_at')->count());
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
            'name' => 'Other Work Lock Team',
            'slug' => 'other-work-lock-team',
            'is_active' => true,
        ]);

        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'is_head_manager' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $team];
    }

    private function createActiveOtherWork(User $user, Team $team): void
    {
        $workSessionId = DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'other-work-lock-session',
            'started_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(TimeTrackingDatabaseTable::OTHER_WORK_CATEGORIES)->insert([
            'public_id' => (string) Str::ulid(),
            'scope_type' => 'team',
            'scope_id' => $team->id,
            'category_key' => 'court_call',
            'label_pl' => 'Telefon do sądu',
            'label_en' => 'Court call',
            'requires_comment' => true,
            'auto_approval_enabled' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(TimeTrackingDatabaseTable::OTHER_WORK)->insert([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'category_key' => 'court_call',
            'description' => 'Calling the court clerk.',
            'approval_status' => 'pending',
            'started_at' => now()->subMinutes(20),
            'requires_manager_review' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignDirectPermissionInTeam(User $user, Team $team, string $permissionName): void
    {
        $permission = Permission::query()->where('name', $permissionName)->firstOrFail();

        DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)->insert([
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
