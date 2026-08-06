<?php

declare(strict_types=1);

namespace Tests\Feature\TimeTracking;

use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Modules\Core\Identity\Application\Admin\ImpersonationSimulationStore;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Core\Users\Application\Permissions\UserPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class UserTimeReportRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_own_time_report_for_active_team(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($user, $team);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::USER_REPORT);

        DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insert([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'feature-session',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => '2026-08-01 09:30:00+00',
            'exact_seconds' => 5400,
            'closure_reason' => 'logout',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/work-time?range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/UserReport')
                ->where('dailyTable.key', 'users.work-time.daily')
                ->where('workSessionsTable.key', 'users.work-time.work-sessions')
                ->where('breaksTable.key', 'users.work-time.breaks')
                ->where('correctionsTable.key', 'users.work-time.corrections')
                ->where('filters.range', 'custom')
                ->where('summary.totalSeconds', 5400)
                ->has('dailyRows', 1)
                ->has('workSessionRows', 1)
                ->where('dailyRows.0.workSeconds', 5400)
                ->where('dailyRows.0.countedSeconds', 5400));
    }

    public function test_user_can_request_correction_for_own_visible_work_session(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($user, $team);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::USER_REPORT);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::USER_CORRECTION_REQUEST_STORE);
        $workSessionPublicId = (string) Str::ulid();

        $workSessionId = DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insertGetId([
            'public_id' => $workSessionPublicId,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'correction-source-session',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => '2026-08-01 09:30:00+00',
            'exact_seconds' => 5400,
            'closure_reason' => 'logout',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->from('/user/work-time')
            ->post('/user/work-time/corrections', [
                'source_type' => 'work_session',
                'source_public_id' => $workSessionPublicId,
                'description' => 'Please correct the end time for this work session.',
                'proposed_started_at' => '2026-08-01T08:00:00+02:00',
                'proposed_ended_at' => '2026-08-01T10:00:00+02:00',
            ])
            ->assertRedirect('/user/work-time')
            ->assertSessionHas('flash.messages.0.key', 'flash.time_tracking.user_correction_requested');

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_REQUESTS, [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'work_session_id' => $workSessionId,
            'source_type' => 'work_session',
            'source_id' => $workSessionId,
            'status' => 'pending',
            'request_type' => 'exact_change',
        ]);
        $correctionId = DB::table(TimeTrackingDatabaseTable::CORRECTION_REQUESTS)->where('source_id', $workSessionId)->value('id');

        self::assertIsNumeric($correctionId);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_PROPOSALS, [
            'correction_request_id' => (int) $correctionId,
            'original_exact_seconds' => 5400,
            'proposed_exact_seconds' => 7200,
        ]);
    }

    public function test_user_break_details_flag_daily_limit_excess(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($user, $team);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::USER_REPORT);

        DB::table(TimeTrackingDatabaseTable::BREAK_POLICIES)->insert([
            'public_id' => (string) Str::ulid(),
            'scope_type' => 'team',
            'scope_id' => $team->id,
            'daily_limit_seconds' => 1800,
            'maximum_single_break_seconds' => 14400,
            'warning_before_maximum_seconds' => 900,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $workSessionId = DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'break-limit-session',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => '2026-08-01 12:00:00+00',
            'exact_seconds' => 14400,
            'closure_reason' => 'logout',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([['09:00:00', '09:20:00'], ['10:00:00', '10:20:00']] as [$start, $end]) {
            DB::table(TimeTrackingDatabaseTable::BREAKS)->insert([
                'public_id' => (string) Str::ulid(),
                'work_session_id' => $workSessionId,
                'user_id' => $user->id,
                'team_id' => $team->id,
                'started_at' => '2026-08-01 '.$start.'+00',
                'ended_at' => '2026-08-01 '.$end.'+00',
                'exact_seconds' => 1200,
                'closure_reason' => 'normal',
                'requires_manager_review' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/work-time?range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/UserReport')
                ->has('breakRows', 2)
                ->where('breakRows.0.breakLimitStatus', 'exceeded')
                ->where('breakRows.0.excessBreakSeconds', 600));
    }

    public function test_user_report_requires_route_permission(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($user, $team);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/work-time')
            ->assertForbidden();
    }

    public function test_untracked_user_team_does_not_see_or_open_work_time_even_with_permission(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::USER_REPORT);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->where('auth.availableApplicationRoutes', fn (mixed $routes): bool => ! self::iterableContains($routes, TimeTrackingPermissionCatalog::USER_REPORT)));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/work-time')
            ->assertForbidden();
    }

    public function test_user_can_view_user_panel_for_active_team(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->where('auth.availableApplicationRoutes', fn (mixed $routes): bool => self::iterableContains($routes, UserPermissionCatalog::USERS_PROFILE))
                ->has('profile.notificationEmails', 1)
                ->has('profile.notificationTypes'));
    }

    public function test_manager_can_view_time_report_for_hierarchy_scope(): void
    {
        [$manager, $team] = $this->userWithTeam();
        $report = User::factory()->create(['name' => 'Scoped Report']);
        $outside = User::factory()->create(['name' => 'Outside User']);
        $this->addUserToTeam($report, $team);
        $this->addUserToTeam($outside, $team);
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($manager, $team, TimeTrackingPermissionCatalog::MANAGER_REPORT);
        $this->createManagerRelationship($manager, $report, $team);

        DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insert([
            [
                'public_id' => (string) Str::ulid(),
                'user_id' => $report->id,
                'team_id' => $team->id,
                'laravel_session_id' => 'report-session',
                'started_at' => '2026-08-01 08:00:00+00',
                'ended_at' => '2026-08-01 09:00:00+00',
                'exact_seconds' => 3600,
                'closure_reason' => 'logout',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'public_id' => (string) Str::ulid(),
                'user_id' => $outside->id,
                'team_id' => $team->id,
                'laravel_session_id' => 'outside-session',
                'started_at' => '2026-08-01 08:00:00+00',
                'ended_at' => '2026-08-01 10:00:00+00',
                'exact_seconds' => 7200,
                'closure_reason' => 'logout',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($manager)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/time-tracking/manager-report?range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/ManagerReport')
                ->where('table.key', 'time-tracking.manager-report')
                ->where('table.exports.endpoint', route('exports.data-table'))
                ->where('scope.visibleUsers', 1)
                ->where('scope.headManager', false)
                ->where('teamSummary.visibleUsers', 1)
                ->where('teamSummary.working', 0)
                ->where('teamSummary.noSession', 1)
                ->has('statusFeed', 2)
                ->where('statusFeed.0.userName', 'Scoped Report')
                ->where('statusFeed.0.status', 'logout')
                ->where('summary.totalSeconds', 3600)
                ->has('rows', 1)
                ->where('rows.0.userName', 'Scoped Report')
                ->where('rows.0.type', 'work'));
    }

    public function test_manager_can_view_scoped_work_time_operations_with_work_sessions(): void
    {
        [$manager, $team] = $this->userWithTeam();
        $report = User::factory()->create(['name' => 'Scoped Operations Report']);
        $outside = User::factory()->create(['name' => 'Outside Operations User']);
        $this->addUserToTeam($report, $team);
        $this->addUserToTeam($outside, $team);
        $this->activateTimeTracking($team);
        $this->enableTracking($report, $team);
        $this->enableTracking($outside, $team);
        $this->assignDirectPermissionInTeam($manager, $team, TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_SUMMARY);
        $this->assignDirectPermissionInTeam($manager, $team, TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSIONS);
        $this->assignDirectPermissionInTeam($manager, $team, TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSION_SHOW);
        $this->createManagerRelationship($manager, $report, $team);
        $scopedSessionPublicId = (string) Str::ulid();
        $outsideSessionPublicId = (string) Str::ulid();

        DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insert([
            [
                'public_id' => $scopedSessionPublicId,
                'user_id' => $report->id,
                'team_id' => $team->id,
                'laravel_session_id' => 'manager-operations-session',
                'started_at' => '2026-08-01 08:00:00+00',
                'ended_at' => '2026-08-01 09:00:00+00',
                'exact_seconds' => 3600,
                'closure_reason' => 'logout',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'public_id' => $outsideSessionPublicId,
                'user_id' => $outside->id,
                'team_id' => $team->id,
                'laravel_session_id' => 'outside-operations-session',
                'started_at' => '2026-08-01 08:00:00+00',
                'ended_at' => '2026-08-01 10:00:00+00',
                'exact_seconds' => 7200,
                'closure_reason' => 'logout',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($manager)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/manager/work-time/work-sessions?team='.$team->public_id.'&range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminOperations')
                ->where('surface', 'manager')
                ->where('section', 'work_sessions')
                ->where('filters.team', (string) $team->public_id)
                ->where('teamOptions.0.publicId', (string) $team->public_id)
                ->where('userOptions.0.name', 'Scoped Operations Report')
                ->where('summary.totalSeconds', 3600)
                ->has('workSessionRows', 1)
                ->where('workSessionRows.0.userName', 'Scoped Operations Report')
                ->where('workSessionRows.0.exactSeconds', 3600));

        $this->actingAs($manager)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/manager/work-time/work-sessions/'.$scopedSessionPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminWorkSessionDetail')
                ->where('surface', 'manager')
                ->where('backHref', route('manager.work-time.work-sessions.index')));

        $this->actingAs($manager)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/manager/work-time/work-sessions/'.$outsideSessionPublicId)
            ->assertForbidden();
    }

    public function test_manager_report_requires_route_permission(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/time-tracking/manager-report')
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/manager/work-time/summary')
            ->assertForbidden();
    }

    public function test_manager_routes_require_real_manager_scope_even_with_permissions(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::MANAGER_PANEL);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::MANAGER_REPORT);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->where('auth.availableApplicationRoutes', function (mixed $routes): bool {
                    if (! is_iterable($routes)) {
                        return false;
                    }

                    foreach ($routes as $route) {
                        if ($route === TimeTrackingPermissionCatalog::MANAGER_PANEL) {
                            return false;
                        }
                    }

                    return true;
                }));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/manager')
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/time-tracking/manager-report')
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/manager/work-time/work-sessions')
            ->assertForbidden();
    }

    public function test_manager_can_view_manager_panel_for_active_team(): void
    {
        [$manager, $team] = $this->userWithTeam();
        $report = User::factory()->create(['name' => 'Panel Scoped Report']);
        $this->addUserToTeam($report, $team);
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($manager, $team, TimeTrackingPermissionCatalog::MANAGER_PANEL);
        $this->createManagerRelationship($manager, $report, $team);

        $this->actingAs($manager)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/manager')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Manager/Panel')
                ->where('auth.availableApplicationRoutes', fn (mixed $routes): bool => self::iterableContains($routes, TimeTrackingPermissionCatalog::MANAGER_PANEL)));
    }

    public function test_impersonated_time_tracking_report_does_not_create_official_work_session(): void
    {
        [$target, $team] = $this->userWithTeam();
        $admin = User::factory()->create();
        $this->addUserToTeam($admin, $team);
        $this->activateTimeTracking($team);
        $this->enableTracking($target, $team);
        $this->assignDirectPermissionInTeam($admin, $team, 'impersonation.start');
        $this->assignDirectPermissionInTeam($target, $team, TimeTrackingPermissionCatalog::USER_REPORT);

        $this->actingAs($admin)
            ->withSession($this->impersonationSession($admin, $target, $team))
            ->get('/user/work-time')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/UserReport')
                ->where('summary.totalSeconds', 0)
                ->where('comparison', null));

        $this->assertDatabaseMissing(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $target->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_impersonated_activity_tracker_uses_simulation_state_only(): void
    {
        [$target, $team] = $this->userWithTeam();
        $admin = User::factory()->create();
        $this->addUserToTeam($admin, $team);
        $this->activateTimeTracking($team);
        $this->enableTracking($target, $team);
        $this->assignDirectPermissionInTeam($admin, $team, 'impersonation.start');
        $this->assignDirectPermissionInTeam($target, $team, TimeTrackingPermissionCatalog::ACTIVITY_RECORD);
        $sessionId = '01KYZ4W8SZKVMSP4YV8E8D0001';

        $this->actingAs($admin)
            ->withSession($this->impersonationSession($admin, $target, $team, $sessionId))
            ->postJson('/time-tracking/activity', ['inactive_ms' => 1200])
            ->assertOk()
            ->assertJson([
                'status' => 'active',
                'workEnded' => false,
                'simulated' => true,
            ]);

        self::assertIsArray($this->app->make(ImpersonationSimulationStore::class)->get($sessionId, 'time-tracking.activity'));
        $this->assertDatabaseMissing(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $target->id,
            'team_id' => $team->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function userWithTeam(): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $user = User::factory()->create();
        $team = Team::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Time Tracking Team',
            'slug' => 'time-tracking-team',
            'is_active' => true,
        ]);

        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $team];
    }

    private function addUserToTeam(User $user, Team $team): void
    {
        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createManagerRelationship(User $manager, User $report, Team $team): void
    {
        DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)->insert([
            'public_id' => (string) Str::ulid(),
            'team_id' => $team->id,
            'manager_user_id' => $manager->id,
            'report_user_id' => $report->id,
            'valid_from' => '2026-08-01 00:00:00+00',
            'reason' => 'Feature test manager scope',
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

    private static function iterableContains(mixed $values, string $needle): bool
    {
        if (! is_iterable($values)) {
            return false;
        }

        foreach ($values as $value) {
            if ($value === $needle) {
                return true;
            }
        }

        return false;
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

    private function enableTracking(User $user, Team $team): void
    {
        $assignmentId = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->value('id');

        self::assertIsNumeric($assignmentId);

        $this->app->make(UserTeamTrackingSettings::class)->setEnabledForAssignment((int) $assignmentId, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function impersonationSession(User $admin, User $target, Team $team, string $sessionId = '01KYZ4W8SZKVMSP4YV8E8D0000'): array
    {
        return [
            'active_team_public_id' => (string) $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
            AdministrativeSessionManager::ENTERED_AT => now()->toIso8601String(),
            AdministrativeSessionManager::LAST_ACTIVITY_AT => now()->toIso8601String(),
            ImpersonationManager::SESSION_ID => $sessionId,
            ImpersonationManager::ACTOR_PUBLIC_ID => (string) $admin->public_id,
            ImpersonationManager::ACTOR_TEAM_PUBLIC_ID => (string) $team->public_id,
            ImpersonationManager::USER_PUBLIC_ID => (string) $target->public_id,
            ImpersonationManager::USER_NAME => (string) $target->name,
            ImpersonationManager::TEAM_PUBLIC_ID => (string) $team->public_id,
            ImpersonationManager::TEAM_NAME => (string) $team->name,
            ImpersonationManager::REASON => 'TimeTracking simulation test',
            ImpersonationManager::STARTED_AT => now()->toIso8601String(),
        ];
    }
}
