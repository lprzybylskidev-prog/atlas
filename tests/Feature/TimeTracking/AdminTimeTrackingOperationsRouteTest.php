<?php

declare(strict_types=1);

namespace Tests\Feature\TimeTracking;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\TimeTracking\Application\Contracts\BreakPolicyStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\CorrectionRequestCoordinator;
use App\Modules\Optional\TimeTracking\Application\DTOs\ExactTimeChange;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionSourceType;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class AdminTimeTrackingOperationsRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_time_tracking_operations_for_tracked_users(): void
    {
        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $secondTarget = User::factory()->create(['name' => 'Second Operator']);
        $this->assignUserToTeam($secondTarget, $team);
        $this->activateTimeTracking($team);
        $this->enableTracking($target, $team);
        $this->enableTracking($secondTarget, $team);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_SUMMARY);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_WORK_SESSIONS);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_WORK_SESSION_SHOW);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_SHOW);
        $workSessionPublicId = (string) Str::ulid();
        $workSessionId = DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insertGetId([
            'public_id' => $workSessionPublicId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'admin-operations-session',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => '2026-08-01 09:00:00+00',
            'exact_seconds' => 3600,
            'closure_reason' => 'logout',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS)->insert([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'module_key' => 'system',
            'context_key' => 'System',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => '2026-08-01 09:00:00+00',
            'exact_seconds' => 3600,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherWorkPublicId = (string) Str::ulid();
        DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK)->insert([
            'public_id' => $otherWorkPublicId,
            'work_session_id' => $workSessionId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'category_key' => null,
            'description' => 'Court records review.',
            'end_note' => null,
            'approval_status' => 'pending',
            'started_at' => '2026-08-01 08:15:00+00',
            'ended_at' => '2026-08-01 08:45:00+00',
            'exact_seconds' => 1800,
            'closure_reason' => 'normal',
            'requires_manager_review' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insert([
            'public_id' => (string) Str::ulid(),
            'user_id' => $secondTarget->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'admin-operations-second-session',
            'started_at' => '2026-08-01 07:00:00+00',
            'ended_at' => '2026-08-01 07:30:00+00',
            'exact_seconds' => 1800,
            'closure_reason' => 'logout',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/summary?team='.$team->public_id.'&range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminOperations')
                ->where('section', 'daily')
                ->where('navigation.breadcrumbs.1.label', 'Panel administratora')
                ->where('navigation.breadcrumbs.2.label', 'Ewidencja czasu')
                ->where('dailyTable.key', 'admin.time-tracking.operations.daily')
                ->where('otherWorkTable.key', 'admin.time-tracking.operations.other-work')
                ->where('workSessionsTable.key', 'admin.time-tracking.operations.work-sessions')
                ->where('dailyTable.exports.endpoint', route('admin.exports.data-table'))
                ->where('auth.availableAdminRoutes', fn (mixed $routes): bool => $this->iterableContains($routes, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_SHOW))
                ->where('filters.team', $team->public_id)
                ->has('teamOptions', 1)
                ->has('userOptions', 2)
                ->has('userOptionsByTeam.'.$team->public_id, 2)
                ->where('moduleOptions.0', 'System')
                ->where('moduleOptionsByTeam.'.$team->public_id.'.0', 'System')
                ->where('dailyTable.state.columns', fn (mixed $columns): bool => $this->iterableContains($columns, 'countedDuration'))
                ->where('dailyTable.state.columns', fn (mixed $columns): bool => $this->iterableContains($columns, 'workDuration'))
                ->where('dailyTable.state.columns', fn (mixed $columns): bool => $this->iterableContains($columns, 'breakDuration'))
                ->where('summary.totalSeconds', 5400)
                ->has('dailyRows', 1)
                ->where('dailyRows.0.userName', '')
                ->where('dailyRows.0.workSeconds', 5400)
                ->where('dailyRows.0.teamName', $team->name)
                ->has('workSessionRows', 2)
                ->where('workSessionRows.0.moduleSegments', 1));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/summary?team='.$team->public_id.'&user='.$target->public_id.'&range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.user', $target->public_id)
                ->where('summary.totalSeconds', 3600)
                ->has('dailyRows', 1)
                ->where('dailyRows.0.userName', $target->name)
                ->where('dailyRows.0.workSeconds', 3600));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/work-sessions?team='.$team->public_id.'&range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminOperations')
                ->where('section', 'work_sessions')
                ->where('workSessionsTable.key', 'admin.time-tracking.operations.work-sessions')
                ->has('workSessionRows', 2));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/work-sessions?team='.$team->public_id.'&range=custom&from=2026-08-01&to=2026-08-01&type=break')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.type', 'all')
                ->has('workSessionRows', 2));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/work-sessions?team='.$team->public_id.'&range=custom&from=2026-08-01&to=2026-08-01&module=System&closure_reason=logout&user='.$target->public_id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.user', $target->public_id)
                ->where('filters.module', '')
                ->where('filters.closure_reason', 'logout')
                ->has('workSessionRows', 1));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/work-sessions/'.$workSessionPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminWorkSessionDetail')
                ->where('kind', 'work_session')
                ->where('record.0.key', 'public_id')
                ->where('record.0.value', $workSessionPublicId)
                ->where('sections.0.title', 'pages.time_tracking.admin_detail.sections.module_segments')
                ->has('sections.0.rows', 1));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/other-work/'.$otherWorkPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminOtherWorkDetail')
                ->where('kind', 'other_work')
                ->where('record.0.key', 'public_id')
                ->where('record.0.value', $otherWorkPublicId));
    }

    public function test_admin_time_tracking_operations_requires_dedicated_permission(): void
    {
        [$admin, , $team] = $this->adminTargetAndTeam();
        $this->activateTimeTracking($team);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/daily-summary')
            ->assertNotFound();

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/summary')
            ->assertForbidden();
    }

    public function test_admin_can_terminate_active_work_session_with_audit_and_notification(): void
    {
        Queue::fake();

        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($target, $team);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_TERMINATE_SESSION);
        $sessionPublicId = (string) Str::ulid();
        DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insert([
            'public_id' => $sessionPublicId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'admin-terminate-session',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => null,
            'exact_seconds' => null,
            'closure_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/work-sessions/'.$sessionPublicId.'/terminate', [
                'reason' => 'Operator account was deactivated during an incident.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_WORK_SESSIONS, [
            'public_id' => $sessionPublicId,
            'closure_reason' => 'administrative_termination',
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'time_tracking',
            'action' => 'time_tracking.admin_work_session_terminated',
            'actor_public_id' => $admin->public_id,
            'target_public_id' => $target->public_id,
            'team_public_id' => $team->public_id,
        ]);
        $this->assertDatabaseHas(DatabaseTable::NOTIFICATIONS, [
            'type' => 'time_tracking.admin_action',
            'title' => 'notifications.time_tracking.admin_action.work_session_terminated.title',
        ]);
    }

    public function test_admin_can_force_close_active_break_and_other_work_locks(): void
    {
        Queue::fake();

        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($target, $team);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_BREAK_FORCE_CLOSE);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_FORCE_CLOSE);
        $workSessionId = $this->insertOpenWorkSession($target, $team);
        $breakPublicId = (string) Str::ulid();
        DB::table(DatabaseTable::TIME_TRACKING_BREAKS)->insert([
            'public_id' => $breakPublicId,
            'work_session_id' => $workSessionId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'started_at' => '2026-08-01 08:30:00+00',
            'ended_at' => null,
            'exact_seconds' => null,
            'closure_reason' => null,
            'requires_manager_review' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/breaks/'.$breakPublicId.'/force-close', [
                'reason' => 'Forced close requested by operations after account lockout.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_BREAKS, [
            'public_id' => $breakPublicId,
            'closure_reason' => 'forced',
            'requires_manager_review' => true,
        ]);

        $otherWorkPublicId = (string) Str::ulid();
        DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK)->insert([
            'public_id' => $otherWorkPublicId,
            'work_session_id' => $workSessionId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'category_key' => null,
            'description' => 'Court call.',
            'end_note' => null,
            'approval_status' => 'approved',
            'started_at' => '2026-08-01 09:30:00+00',
            'ended_at' => null,
            'exact_seconds' => null,
            'closure_reason' => null,
            'requires_manager_review' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/other-work/'.$otherWorkPublicId.'/force-close', [
                'reason' => 'Maintenance interruption had to be resolved by operations.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_OTHER_WORK, [
            'public_id' => $otherWorkPublicId,
            'closure_reason' => 'forced',
            'approval_status' => 'under_review',
            'requires_manager_review' => true,
        ]);
    }

    public function test_admin_can_convert_excess_break_time_with_audited_final_correction(): void
    {
        Queue::fake();

        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($target, $team);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_SUMMARY);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_BREAKS);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_BREAK_SHOW);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_BREAK_CONVERT_EXCESS);
        $this->app->make(BreakPolicyStore::class)->setTeamPolicy($team->id, 1800, 14400);
        $workSessionId = DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $target->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'admin-convert-excess-session',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => '2026-08-01 12:00:00+00',
            'exact_seconds' => 14400,
            'closure_reason' => 'logout',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(DatabaseTable::TIME_TRACKING_BREAKS)->insert([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'started_at' => '2026-08-01 08:30:00+00',
            'ended_at' => '2026-08-01 08:50:00+00',
            'exact_seconds' => 1200,
            'closure_reason' => 'normal',
            'requires_manager_review' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $breakPublicId = (string) Str::ulid();
        $breakId = DB::table(DatabaseTable::TIME_TRACKING_BREAKS)->insertGetId([
            'public_id' => $breakPublicId,
            'work_session_id' => $workSessionId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'started_at' => '2026-08-01 09:00:00+00',
            'ended_at' => '2026-08-01 09:20:00+00',
            'exact_seconds' => 1200,
            'closure_reason' => 'normal',
            'requires_manager_review' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/breaks?team='.$team->public_id.'&range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('breakRows.0.publicId', $breakPublicId)
                ->where('breakRows.0.exactSeconds', 1200)
                ->where('breakRows.0.breakLimitStatus', 'exceeded')
                ->where('breakRows.0.excessBreakSeconds', 600)
                ->where('breakRows.0.availableActions.0', 'convert_excess'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/breaks/'.$breakPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminBreakDetail')
                ->where('record.15.key', 'available_actions')
                ->where('record.15.value', 'convert_excess_break'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/breaks/'.$breakPublicId.'/convert-excess', [
                'converted_seconds' => 600,
                'reason' => 'Reviewed the daily break excess and converted only the overtime part.',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.messages');

        $correctionId = DB::table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)
            ->where('source_type', 'break')
            ->where('source_id', $breakId)
            ->value('id');

        self::assertIsNumeric($correctionId);

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS, [
            'id' => $correctionId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'source_type' => 'break',
            'source_id' => $breakId,
            'request_type' => 'exact_change',
            'status' => 'corrected',
            'decided_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_CORRECTION_PROPOSALS, [
            'correction_request_id' => $correctionId,
            'original_exact_seconds' => 1200,
            'final_exact_seconds' => 600,
        ]);
        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_CORRECTION_HISTORY, [
            'correction_request_id' => $correctionId,
            'actor_user_id' => $admin->id,
            'action' => 'corrected',
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'time_tracking',
            'action' => 'time_tracking.admin_break_excess_converted',
            'actor_public_id' => $admin->public_id,
            'target_public_id' => $target->public_id,
            'team_public_id' => $team->public_id,
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/breaks?team='.$team->public_id.'&range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('breakRows.0.publicId', $breakPublicId)
                ->where('breakRows.0.exactSeconds', 600)
                ->where('breakRows.0.breakLimitStatus', 'within_limit')
                ->where('breakRows.0.excessBreakSeconds', 0)
                ->where('breakRows.0.availableActions', []));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/summary?team='.$team->public_id.'&user='.$target->public_id.'&range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dailyRows.0.workSeconds', 14400)
                ->where('dailyRows.0.breakSeconds', 1800)
                ->where('dailyRows.0.countedSeconds', 14400));
    }

    public function test_admin_can_decide_pending_other_work_records(): void
    {
        Queue::fake();

        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($target, $team);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_DECIDE);
        $workSessionId = $this->insertOpenWorkSession($target, $team);
        $otherWorkPublicId = (string) Str::ulid();

        DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK)->insert([
            'public_id' => $otherWorkPublicId,
            'work_session_id' => $workSessionId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'category_key' => null,
            'description' => 'Court records review.',
            'end_note' => null,
            'approval_status' => 'pending',
            'started_at' => '2026-08-01 09:00:00+00',
            'ended_at' => '2026-08-01 10:00:00+00',
            'exact_seconds' => 3600,
            'closure_reason' => 'normal',
            'requires_manager_review' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/other-work?team='.$team->public_id.'&range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('section', 'other_work')
                ->where('otherWorkRows.0.publicId', $otherWorkPublicId)
                ->where('otherWorkRows.0.availableActions.0', 'approve')
                ->where('otherWorkRows.0.availableActions.1', 'reject'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/other-work/'.$otherWorkPublicId.'/decide', [
                'decision' => 'approve',
                'reason' => 'Reviewed field evidence and accepted the record.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_OTHER_WORK, [
            'public_id' => $otherWorkPublicId,
            'approval_status' => 'approved',
            'requires_manager_review' => false,
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'time_tracking',
            'action' => 'time_tracking.other_work_approved',
            'actor_public_id' => $admin->public_id,
            'target_public_id' => $target->public_id,
            'team_public_id' => $team->public_id,
        ]);
        $this->assertDatabaseHas(DatabaseTable::NOTIFICATIONS, [
            'type' => 'time_tracking.admin_action',
            'title' => 'notifications.time_tracking.admin_action.other_work_decided.title',
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/other-work/'.$otherWorkPublicId.'/decide', [
                'decision' => 'reject',
                'reason' => 'Second decision should not overwrite the first one.',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_OTHER_WORK, [
            'public_id' => $otherWorkPublicId,
            'approval_status' => 'approved',
            'requires_manager_review' => false,
        ]);
    }

    public function test_admin_can_open_operation_details_for_selected_tracked_team_from_admin_active_team(): void
    {
        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $adminTeam = Team::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Administration Scope',
            'slug' => 'administration-scope',
            'is_active' => true,
        ]);
        $this->assignUserToTeam($admin, $adminTeam);
        $this->activateTimeTracking($team);
        $this->activateTimeTracking($adminTeam);
        $this->enableTracking($target, $team);
        $this->assignDirectPermissionInTeam($admin, $adminTeam, TimeTrackingPermissionCatalog::ADMIN_WORK_SESSION_SHOW);
        $this->assignDirectPermissionInTeam($admin, $adminTeam, TimeTrackingPermissionCatalog::ADMIN_BREAK_SHOW);
        $this->assignDirectPermissionInTeam($admin, $adminTeam, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK);
        $this->assignDirectPermissionInTeam($admin, $adminTeam, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_SHOW);
        $this->assignDirectPermissionInTeam($admin, $adminTeam, TimeTrackingPermissionCatalog::ADMIN_CORRECTION_SHOW);
        $workSessionId = $this->insertOpenWorkSession($target, $team);
        $workSessionPublicId = $this->stringValue(DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)
            ->where('id', $workSessionId)
            ->value('public_id'));
        $breakPublicId = (string) Str::ulid();
        $otherWorkPublicId = (string) Str::ulid();
        $correction = $this->app->make(CorrectionRequestCoordinator::class)->requestDescriptive(
            $target->id,
            $team->id,
            $workSessionId,
            'Forgot to report missing time.',
            new \DateTimeImmutable('2026-08-01 11:00:00+00'),
        );

        DB::table(DatabaseTable::TIME_TRACKING_BREAKS)->insert([
            'public_id' => $breakPublicId,
            'work_session_id' => $workSessionId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'started_at' => '2026-08-01 08:30:00+00',
            'ended_at' => '2026-08-01 08:45:00+00',
            'exact_seconds' => 900,
            'closure_reason' => 'normal',
            'requires_manager_review' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK)->insert([
            'public_id' => $otherWorkPublicId,
            'work_session_id' => $workSessionId,
            'user_id' => $target->id,
            'team_id' => $team->id,
            'category_key' => null,
            'description' => 'Court records review.',
            'end_note' => null,
            'approval_status' => 'pending',
            'started_at' => '2026-08-01 09:00:00+00',
            'ended_at' => '2026-08-01 10:00:00+00',
            'exact_seconds' => 3600,
            'closure_reason' => 'normal',
            'requires_manager_review' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($adminTeam))
            ->get('/admin/work-time/work-sessions/'.$workSessionPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminWorkSessionDetail')
                ->where('kind', 'work_session')
                ->where('record.0.value', $workSessionPublicId));

        $this->actingAs($admin)
            ->withSession($this->adminSession($adminTeam))
            ->get('/admin/work-time/breaks/'.$breakPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminBreakDetail')
                ->where('kind', 'break')
                ->where('record.0.value', $breakPublicId));

        $this->actingAs($admin)
            ->withSession($this->adminSession($adminTeam))
            ->get('/admin/work-time/other-work/'.$otherWorkPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminOtherWorkDetail')
                ->where('kind', 'other_work')
                ->where('record.0.value', $otherWorkPublicId));

        $this->actingAs($admin)
            ->withSession($this->adminSession($adminTeam))
            ->get('/admin/work-time/corrections/'.$correction->publicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminCorrectionDetail')
                ->where('kind', 'correction')
                ->where('record.0.value', $correction->publicId));
    }

    public function test_admin_can_decide_corrections_and_create_manual_entries(): void
    {
        Queue::fake();

        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($target, $team);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_SUMMARY);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_WORK_SESSIONS);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_BREAKS);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_CORRECTION_DECIDE);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_MANUAL_ENTRY);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_MANUAL_ENTRY_STORE);
        $request = $this->app->make(CorrectionRequestCoordinator::class)->requestDescriptive(
            $target->id,
            $team->id,
            null,
            'Forgot to report missing time.',
            new \DateTimeImmutable('2026-08-01 08:00:00+00'),
        );

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/corrections/'.$request->publicId.'/decide', [
                'decision' => 'correct',
                'final_started_at' => '2026-08-01 08:00:00+02:00',
                'final_ended_at' => '2026-08-01 09:00:00+02:00',
                'reason' => 'Final correction without an intermediate ownership step.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS, [
            'public_id' => $request->publicId,
            'status' => 'corrected',
            'decided_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/corrections/manual-entry')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminManualEntryCreate')
                ->has('teamOptions')
                ->has('userOptionsByTeam')
                ->has('otherWorkCategoryOptionsByTeam')
            );

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/corrections/manual-entry', [
                'entry_kind' => 'work_session',
                'user_public_id' => $target->public_id,
                'team_public_id' => $team->public_id,
                'final_started_at' => '2026-08-02 08:00:00+02:00',
                'final_ended_at' => '2026-08-02 09:00:00+02:00',
                'reason' => 'Manual entry for approved exceptional missing time.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS, [
            'user_id' => $target->id,
            'team_id' => $team->id,
            'request_type' => 'manual_entry',
            'status' => 'corrected',
            'decided_by_user_id' => $admin->id,
            'source_type' => 'work_session',
        ]);
        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_WORK_SESSIONS, [
            'user_id' => $target->id,
            'team_id' => $team->id,
            'closure_reason' => 'manual_entry',
            'exact_seconds' => 3600,
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/corrections/manual-entry', [
                'entry_kind' => 'break',
                'user_public_id' => $target->public_id,
                'team_public_id' => $team->public_id,
                'final_started_at' => '2026-08-02 10:00:00+02:00',
                'final_ended_at' => '2026-08-02 10:15:00+02:00',
                'reason' => 'Manual break entry for approved administrative evidence.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_BREAKS, [
            'user_id' => $target->id,
            'team_id' => $team->id,
            'closure_reason' => 'normal',
            'requires_manager_review' => false,
            'exact_seconds' => 900,
        ]);
        $breakId = $this->intValue(DB::table(DatabaseTable::TIME_TRACKING_BREAKS)
            ->where('user_id', $target->id)
            ->where('team_id', $team->id)
            ->where('exact_seconds', 900)
            ->value('id'));
        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS, [
            'user_id' => $target->id,
            'team_id' => $team->id,
            'request_type' => 'manual_entry',
            'status' => 'corrected',
            'source_type' => 'break',
            'source_id' => $breakId,
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/corrections/manual-entry', [
                'entry_kind' => 'other_work',
                'user_public_id' => $target->public_id,
                'team_public_id' => $team->public_id,
                'final_started_at' => '2026-08-02 11:00:00+02:00',
                'final_ended_at' => '2026-08-02 11:30:00+02:00',
                'reason' => 'Manual other work entry for approved administrative evidence.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_OTHER_WORK, [
            'user_id' => $target->id,
            'team_id' => $team->id,
            'approval_status' => 'approved',
            'requires_manager_review' => false,
            'exact_seconds' => 1800,
        ]);
        $otherWorkId = $this->intValue(DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK)
            ->where('user_id', $target->id)
            ->where('team_id', $team->id)
            ->where('exact_seconds', 1800)
            ->value('id'));
        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS, [
            'user_id' => $target->id,
            'team_id' => $team->id,
            'request_type' => 'manual_entry',
            'status' => 'corrected',
            'source_type' => 'other_work',
            'source_id' => $otherWorkId,
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/work-sessions?team='.$team->public_id.'&range=custom&from=2026-08-02&to=2026-08-02')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('workSessionRows', 1)
                ->where('workSessionRows.0.closureReason', 'manual_entry'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/breaks?team='.$team->public_id.'&range=custom&from=2026-08-02&to=2026-08-02')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('breakRows', 1)
                ->where('breakRows.0.publicId', fn (string $publicId): bool => $publicId !== ''));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/other-work?team='.$team->public_id.'&range=custom&from=2026-08-02&to=2026-08-02')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('otherWorkRows', 1)
                ->where('otherWorkRows.0.status', 'approved'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/summary?team='.$team->public_id.'&user='.$target->public_id.'&range=custom&from=2026-08-02&to=2026-08-02')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('dailyRows', 1)
                ->where('dailyRows.0.workSeconds', 3600)
                ->where('dailyRows.0.breakSeconds', 900)
                ->where('dailyRows.0.acceptedOtherWorkSeconds', 1800)
                ->where('dailyRows.0.countedSeconds', 5400));
    }

    public function test_admin_corrections_index_exposes_source_type_column(): void
    {
        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($target, $team);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_CORRECTIONS);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_CORRECTION_SHOW);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_CORRECTION_DECIDE);
        $workSessionId = DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $target->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'admin-correction-source-session',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => '2026-08-01 09:00:00+00',
            'exact_seconds' => 3600,
            'closure_reason' => 'logout',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->make(CorrectionRequestCoordinator::class)->requestSourceExactChange(
            $target->id,
            $team->id,
            CorrectionSourceType::WorkSession,
            (int) $workSessionId,
            'Correction source should be visible in the Admin correction table.',
            new ExactTimeChange(
                startedAt: new \DateTimeImmutable('2026-08-01 08:00:00+00'),
                endedAt: new \DateTimeImmutable('2026-08-01 09:00:00+00'),
                exactSeconds: 3600,
            ),
            new ExactTimeChange(
                startedAt: new \DateTimeImmutable('2026-08-01 08:15:00+00'),
                endedAt: new \DateTimeImmutable('2026-08-01 09:15:00+00'),
                exactSeconds: 3600,
            ),
            new \DateTimeImmutable('2026-08-01 10:00:00+00'),
        );

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/corrections?team='.$team->public_id.'&range=custom&from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminOperations')
                ->where('section', 'corrections')
                ->where('auth.availableAdminRoutes', fn (mixed $routes): bool => $this->iterableContains($routes, TimeTrackingPermissionCatalog::ADMIN_CORRECTION_DECIDE))
                ->where('correctionsTable.state.columns', fn (mixed $columns): bool => $this->iterableContains($columns, 'sourceType'))
                ->has('correctionRows', 1)
                ->where('correctionRows.0.sourceType', 'work_session')
                ->where('correctionRows.0.originalStartedAt', '2026-08-01 08:00:00+02')
                ->where('correctionRows.0.proposedStartedAt', '2026-08-01 08:15:00+02')
                ->where('correctionRows.0.availableActions.0', 'reject')
                ->where('correctionRows.0.availableActions.1', 'correct'));

        $correctionPublicId = $this->stringValue(DB::table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)
            ->where('source_type', 'work_session')
            ->where('source_id', $workSessionId)
            ->value('public_id'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/corrections/'.$correctionPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminCorrectionDetail')
                ->where('record', fn (mixed $record): bool => $this->recordContainsPair($record, 'available_actions', 'reject,correct'))
                ->where('sections.0.rows.0.proposed_started_at', '2026-08-01 08:15:00+02'));
    }

    public function test_admin_can_manage_team_other_work_categories(): void
    {
        [$admin, $target, $team] = $this->adminTargetAndTeam();
        $this->activateTimeTracking($team);
        $this->enableTracking($target, $team);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_CATEGORY_INDEX);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_CATEGORY_CREATE);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_CATEGORY_STORE);
        $this->assignDirectPermissionInTeam($admin, $team, TimeTrackingPermissionCatalog::ADMIN_OTHER_WORK_CATEGORY_DEACTIVATE);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/other-work/categories')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminOtherWorkCategories')
                ->where('navigation.breadcrumbs.1.label', 'Panel administratora')
                ->where('navigation.breadcrumbs.2.label', 'Ewidencja czasu')
                ->where('navigation.breadcrumbs.3.label', 'Praca poza komputerem')
                ->where('navigation.breadcrumbs.4.label', 'Kategorie')
                ->has('teamOptions', 1)
                ->has('categories', 0));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/other-work/categories/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminOtherWorkCategoryCreate')
                ->where('defaultTeamPublicId', $team->public_id)
                ->where('navigation.breadcrumbs.5.label', 'Utwórz kategorię'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/work-time/other-work/categories', [
                'team_public_id' => $team->public_id,
                'category_key' => 'court_call',
                'label_pl' => 'Telefon do sądu',
                'label_en' => 'Court call',
                'requires_comment' => true,
                'auto_approval_enabled' => false,
                'reason' => 'Adding a team category needed for court calls.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_OTHER_WORK_CATEGORIES, [
            'scope_type' => 'team',
            'scope_id' => $team->id,
            'category_key' => 'court_call',
            'is_active' => true,
            'requires_comment' => true,
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/other-work/categories?team='.$team->public_id.'&status=active')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeTracking/AdminOtherWorkCategories')
                ->where('filters.team', $team->public_id)
                ->where('filters.status', 'active')
                ->has('categories', 1)
                ->where('categories.0.key', 'court_call')
                ->where('categories.0.labelPl', 'Telefon do sądu')
                ->where('categories.0.isActive', true));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->delete('/admin/work-time/other-work/categories/court_call', [
                'team_public_id' => $team->public_id,
                'reason' => 'Category is no longer used by this team.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_OTHER_WORK_CATEGORIES, [
            'scope_type' => 'team',
            'scope_id' => $team->id,
            'category_key' => 'court_call',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/work-time/other-work/categories?team='.$team->public_id.'&status=inactive')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('categories', 1)
                ->where('categories.0.key', 'court_call')
                ->where('categories.0.isActive', false));
    }

    /**
     * @return array{0: User, 1: User, 2: Team}
     */
    private function adminTargetAndTeam(): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $admin = User::factory()->create();
        $target = User::factory()->create(['name' => 'Tracked Operator']);
        $team = Team::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'TimeTracking Operations Team',
            'slug' => 'time-tracking-operations-team',
            'is_active' => true,
        ]);

        $this->assignUserToTeam($admin, $team);
        $this->assignUserToTeam($target, $team);

        return [$admin, $target, $team];
    }

    private function assignUserToTeam(User $user, Team $team): void
    {
        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
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

    private function enableTracking(User $user, Team $team): void
    {
        $assignmentId = DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->value('id');

        self::assertIsNumeric($assignmentId);

        $this->app->make(UserTeamTrackingSettings::class)->setEnabledForAssignment((int) $assignmentId, true);
    }

    private function insertOpenWorkSession(User $user, Team $team): int
    {
        return (int) DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'laravel_session_id' => 'admin-active-session',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => null,
            'exact_seconds' => null,
            'closure_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
        ];
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function iterableContains(mixed $values, string $needle): bool
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

    private function recordContainsPair(mixed $record, string $key, string $value): bool
    {
        if (! is_iterable($record)) {
            return false;
        }

        foreach ($record as $item) {
            if (is_array($item) && ($item['key'] ?? null) === $key && ($item['value'] ?? null) === $value) {
                return true;
            }
        }

        return false;
    }
}
