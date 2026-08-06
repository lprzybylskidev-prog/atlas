<?php

declare(strict_types=1);

namespace Tests\Integration\TimeTracking;

use App\Modules\Core\Audit\Application\Public\Persistence\AuditDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Notifications\Application\Public\Persistence\NotificationsDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\BreakSessionCoordinator;
use App\Modules\Optional\TimeTracking\Application\Contracts\ActiveTimeLockStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\BreakPolicyStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\OtherWorkCategoryStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\CorrectionRequestCoordinator;
use App\Modules\Optional\TimeTracking\Application\DTOs\ClosedPeriodOverrideAuthorization;
use App\Modules\Optional\TimeTracking\Application\DTOs\ExactTimeChange;
use App\Modules\Optional\TimeTracking\Application\DTOs\OtherWorkCategory;
use App\Modules\Optional\TimeTracking\Application\InactivityCoordinator;
use App\Modules\Optional\TimeTracking\Application\MaintenanceCoordinator;
use App\Modules\Optional\TimeTracking\Application\OtherWorkSessionCoordinator;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\SettlementPeriodCoordinator;
use App\Modules\Optional\TimeTracking\Application\UserTimeReportService;
use App\Modules\Optional\TimeTracking\Application\WorkSessionCoordinator;
use App\Modules\Optional\TimeTracking\Presentation\Http\Middleware\SynchronizeWorkSession;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class TimeTrackingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_team_tracking_is_disabled_by_default_and_enabled_per_assignment(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);

        self::assertFalse($settings->isEnabledForUserTeam($userId, $teamId));

        $settings->setEnabledForAssignment($assignmentId, true);

        self::assertTrue($settings->isEnabledForUserTeam($userId, $teamId));
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::USER_TEAM_SETTINGS, [
            'team_user_assignment_id' => $assignmentId,
            'tracking_enabled' => true,
        ]);

        $settings->setEnabledForAssignment($assignmentId, false);

        self::assertFalse($settings->isEnabledForUserTeam($userId, $teamId));
    }

    public function test_user_team_tracking_uses_only_active_assignments(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId, validTo: '2026-08-01 09:00:00+00');

        $this->app->make(UserTeamTrackingSettings::class)->setEnabledForAssignment($assignmentId, true);

        self::assertFalse($this->app->make(UserTeamTrackingSettings::class)->isEnabledForUserTeam($userId, $teamId));
    }

    public function test_work_session_starts_for_tracked_team_and_ends_with_exact_seconds_on_logout(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $coordinator = $this->app->make(WorkSessionCoordinator::class);

        $this->app->make(UserTeamTrackingSettings::class)->setEnabledForAssignment($assignmentId, true);

        $coordinator->synchronizeActiveTeam(
            userId: $userId,
            teamId: $teamId,
            laravelSessionId: 'session-a',
            occurredAt: $this->instant('2026-08-01 08:00:00'),
            moduleKey: 'system',
            contextKey: 'System',
        );

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'team_id' => $teamId,
            'laravel_session_id' => 'session-a',
            'ended_at' => null,
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::MODULE_CONTEXT_SEGMENTS, [
            'module_key' => 'system',
            'context_key' => 'System',
            'ended_at' => null,
        ]);

        $coordinator->endForLogout($userId, $this->instant('2026-08-01 09:01:05'));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'team_id' => $teamId,
            'closure_reason' => 'logout',
            'exact_seconds' => 3665,
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::MODULE_CONTEXT_SEGMENTS, [
            'module_key' => 'system',
            'context_key' => 'System',
            'exact_seconds' => 3665,
        ]);
    }

    public function test_work_session_transitions_between_tracked_and_untracked_teams(): void
    {
        $userId = $this->createUser();
        $firstTeamId = $this->createTeam('Tracked Team A');
        $secondTeamId = $this->createTeam('Tracked Team B');
        $untrackedTeamId = $this->createTeam('Untracked Team');
        $firstAssignmentId = $this->createAssignment($userId, $firstTeamId);
        $secondAssignmentId = $this->createAssignment($userId, $secondTeamId);
        $this->createAssignment($userId, $untrackedTeamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $coordinator = $this->app->make(WorkSessionCoordinator::class);

        $settings->setEnabledForAssignment($firstAssignmentId, true);
        $settings->setEnabledForAssignment($secondAssignmentId, true);

        $coordinator->synchronizeActiveTeam($userId, $firstTeamId, 'session-a', $this->instant('2026-08-01 08:00:00'));
        $coordinator->synchronizeActiveTeam($userId, $secondTeamId, 'session-a', $this->instant('2026-08-01 09:00:00'));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'team_id' => $firstTeamId,
            'closure_reason' => 'team_switched',
            'exact_seconds' => 3600,
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'team_id' => $secondTeamId,
            'ended_at' => null,
        ]);

        $coordinator->synchronizeActiveTeam($userId, $untrackedTeamId, 'session-a', $this->instant('2026-08-01 09:30:00'));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'team_id' => $secondTeamId,
            'closure_reason' => 'team_untracked',
            'exact_seconds' => 1800,
        ]);
        self::assertSame(0, DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->where('user_id', $userId)->whereNull('ended_at')->count());
    }

    public function test_work_session_synchronizes_same_browser_session_across_multiple_tabs(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $coordinator = $this->app->make(WorkSessionCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);

        $coordinator->synchronizeActiveTeam(
            userId: $userId,
            teamId: $teamId,
            laravelSessionId: 'shared-browser-session',
            occurredAt: $this->instant('2026-08-01 08:00:00'),
            moduleKey: 'system',
            contextKey: 'System',
        );
        $coordinator->synchronizeActiveTeam(
            userId: $userId,
            teamId: $teamId,
            laravelSessionId: 'shared-browser-session',
            occurredAt: $this->instant('2026-08-01 08:05:00'),
            moduleKey: 'files',
            contextKey: 'admin.files.index',
        );

        self::assertSame(1, DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->where('user_id', $userId)->count());
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'team_id' => $teamId,
            'laravel_session_id' => 'shared-browser-session',
            'ended_at' => null,
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::MODULE_CONTEXT_SEGMENTS, [
            'module_key' => 'system',
            'context_key' => 'System',
            'ended_at' => null,
        ]);
    }

    public function test_work_session_supersedes_previous_browser_session_for_same_user_team(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $coordinator = $this->app->make(WorkSessionCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);

        $coordinator->synchronizeActiveTeam($userId, $teamId, 'session-a', $this->instant('2026-08-01 08:00:00'));
        $coordinator->synchronizeActiveTeam($userId, $teamId, 'session-b', $this->instant('2026-08-01 08:10:00'));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'team_id' => $teamId,
            'laravel_session_id' => 'session-a',
            'closure_reason' => 'session_superseded',
            'exact_seconds' => 600,
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'team_id' => $teamId,
            'laravel_session_id' => 'session-b',
            'ended_at' => null,
        ]);
        self::assertSame(1, DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->where('user_id', $userId)->whereNull('ended_at')->count());
    }

    public function test_current_atlas_routes_keep_system_context_inside_active_work_session(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $coordinator = $this->app->make(WorkSessionCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);

        $coordinator->synchronizeActiveTeam(
            userId: $userId,
            teamId: $teamId,
            laravelSessionId: 'session-a',
            occurredAt: $this->instant('2026-08-01 08:00:00'),
            moduleKey: 'system',
            contextKey: 'System',
        );
        $coordinator->synchronizeActiveTeam(
            userId: $userId,
            teamId: $teamId,
            laravelSessionId: 'session-a',
            occurredAt: $this->instant('2026-08-01 08:15:00'),
            moduleKey: 'files',
            contextKey: 'admin.files.index',
        );

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::MODULE_CONTEXT_SEGMENTS, [
            'module_key' => 'system',
            'context_key' => 'System',
            'ended_at' => null,
        ]);

        self::assertSame(1, DB::table(TimeTrackingDatabaseTable::MODULE_CONTEXT_SEGMENTS)->count());
        self::assertSame(1, DB::table(TimeTrackingDatabaseTable::MODULE_CONTEXT_SEGMENTS)->whereNull('ended_at')->count());
    }

    public function test_active_break_and_other_work_locks_are_detected(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $workSessionId = $this->createWorkSession($userId, $teamId);

        $breakPublicId = (string) Str::ulid();
        DB::table(TimeTrackingDatabaseTable::BREAKS)->insert([
            'public_id' => $breakPublicId,
            'work_session_id' => $workSessionId,
            'user_id' => $userId,
            'team_id' => $teamId,
            'started_at' => '2026-08-01 08:30:00+00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lock = $this->app->make(ActiveTimeLockStore::class)->activeForUser($userId);

        self::assertNotNull($lock);
        self::assertSame('break', $lock->type);
        self::assertSame($breakPublicId, $lock->publicId);

        DB::table(TimeTrackingDatabaseTable::BREAKS)->where('public_id', $breakPublicId)->update([
            'ended_at' => '2026-08-01 08:45:00+00',
            'exact_seconds' => 900,
            'updated_at' => now(),
        ]);

        $otherWorkPublicId = (string) Str::ulid();
        DB::table(TimeTrackingDatabaseTable::OTHER_WORK)->insert([
            'public_id' => $otherWorkPublicId,
            'work_session_id' => $workSessionId,
            'user_id' => $userId,
            'team_id' => $teamId,
            'description' => 'Meeting outside Atlas.',
            'started_at' => '2026-08-01 09:00:00+00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lock = $this->app->make(ActiveTimeLockStore::class)->activeForUser($userId);

        self::assertNotNull($lock);
        self::assertSame('other_work', $lock->type);
        self::assertSame($otherWorkPublicId, $lock->publicId);
    }

    public function test_active_time_lock_blocks_team_switch_and_logout_routes(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $workSessionId = $this->createWorkSession($userId, $teamId);

        DB::table(TimeTrackingDatabaseTable::BREAKS)->insert([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $userId,
            'team_id' => $teamId,
            'started_at' => '2026-08-01 08:30:00+00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $middleware = $this->app->make(SynchronizeWorkSession::class);

        foreach (['team.switch', 'logout'] as $routeName) {
            try {
                $middleware->handle($this->requestForRoute($routeName, $userId), static fn (): Response => new Response('ok'));
                self::fail(sprintf('Route [%s] should be blocked by active TimeTracking lock.', $routeName));
            } catch (HttpException $exception) {
                self::assertSame(423, $exception->getStatusCode());
            }
        }
    }

    public function test_break_policy_resolves_user_team_then_team_then_global_then_default(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $policies = $this->app->make(BreakPolicyStore::class);

        $default = $policies->policyForUserTeam($userId, $teamId);

        self::assertSame(900, $default->dailyLimitSeconds);
        self::assertSame(14400, $default->maximumSingleBreakSeconds);
        self::assertSame('default', $default->source);

        $policies->setGlobalPolicy(dailyLimitSeconds: 1200, maximumSingleBreakSeconds: 10800);
        $global = $policies->policyForUserTeam($userId, $teamId);

        self::assertSame(1200, $global->dailyLimitSeconds);
        self::assertSame(10800, $global->maximumSingleBreakSeconds);
        self::assertSame('global', $global->source);

        $policies->setTeamPolicy($teamId, dailyLimitSeconds: 900, maximumSingleBreakSeconds: 7200);
        $team = $policies->policyForUserTeam($userId, $teamId);

        self::assertSame(900, $team->dailyLimitSeconds);
        self::assertSame(7200, $team->maximumSingleBreakSeconds);
        self::assertSame('team', $team->source);

        $policies->setUserTeamPolicy($assignmentId, dailyLimitSeconds: 600, maximumSingleBreakSeconds: 3600);
        $userTeam = $policies->policyForUserTeam($userId, $teamId);

        self::assertSame(600, $userTeam->dailyLimitSeconds);
        self::assertSame(3600, $userTeam->maximumSingleBreakSeconds);
        self::assertSame('user_team', $userTeam->source);
    }

    public function test_break_session_starts_from_active_work_session_and_closes_with_exact_seconds(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $workSessions = $this->app->make(WorkSessionCoordinator::class);
        $breaks = $this->app->make(BreakSessionCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);
        $workSessions->synchronizeActiveTeam($userId, $teamId, 'session-a', $this->instant('2026-08-01 08:00:00'));

        $break = $breaks->start($userId, $this->instant('2026-08-01 08:10:00'));

        self::assertSame($userId, $break->userId);
        self::assertSame($teamId, $break->teamId);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::BREAKS, [
            'user_id' => $userId,
            'team_id' => $teamId,
            'ended_at' => null,
        ]);

        $breaks->end($userId, $this->instant('2026-08-01 08:25:30'));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::BREAKS, [
            'user_id' => $userId,
            'closure_reason' => 'normal',
            'exact_seconds' => 930,
            'requires_manager_review' => false,
        ]);
    }

    public function test_break_session_can_be_forced_closed_for_manager_review(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $workSessions = $this->app->make(WorkSessionCoordinator::class);
        $breaks = $this->app->make(BreakSessionCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);
        $workSessions->synchronizeActiveTeam($userId, $teamId, 'session-a', $this->instant('2026-08-01 08:00:00'));
        $breaks->start($userId, $this->instant('2026-08-01 08:10:00'));

        $breaks->forceClose($userId, $this->instant('2026-08-01 08:18:00'));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::BREAKS, [
            'user_id' => $userId,
            'closure_reason' => 'forced',
            'exact_seconds' => 480,
            'requires_manager_review' => true,
        ]);
    }

    public function test_expired_break_is_closed_at_maximum_duration_and_ends_work_session_for_review(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $policies = $this->app->make(BreakPolicyStore::class);
        $workSessions = $this->app->make(WorkSessionCoordinator::class);
        $breaks = $this->app->make(BreakSessionCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);
        $policies->setUserTeamPolicy($assignmentId, dailyLimitSeconds: 1800, maximumSingleBreakSeconds: 600);
        $workSessions->synchronizeActiveTeam($userId, $teamId, 'session-a', $this->instant('2026-08-01 08:00:00'));
        $breaks->start($userId, $this->instant('2026-08-01 08:10:00'));

        $closed = $breaks->closeExpired($this->instant('2026-08-01 08:25:00'));

        self::assertSame(1, $closed);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::BREAKS, [
            'user_id' => $userId,
            'closure_reason' => 'maximum_duration',
            'exact_seconds' => 600,
            'requires_manager_review' => true,
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'closure_reason' => 'break_maximum_duration',
            'exact_seconds' => 1200,
        ]);
    }

    public function test_due_break_reminder_is_recorded_once_without_publishing_user_notification(): void
    {
        Queue::fake();

        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $policies = $this->app->make(BreakPolicyStore::class);
        $workSessions = $this->app->make(WorkSessionCoordinator::class);
        $breaks = $this->app->make(BreakSessionCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);
        $policies->setUserTeamPolicy($assignmentId, dailyLimitSeconds: 1800, maximumSingleBreakSeconds: 1200, warningBeforeMaximumSeconds: 300);
        $workSessions->synchronizeActiveTeam($userId, $teamId, 'session-a', $this->instant('2026-08-01 08:00:00'));
        $break = $breaks->start($userId, $this->instant('2026-08-01 08:10:00'));

        self::assertSame(0, $breaks->recordDueReminders($this->instant('2026-08-01 08:24:59')));
        self::assertSame(1, $breaks->recordDueReminders($this->instant('2026-08-01 08:25:00')));
        self::assertSame(0, $breaks->recordDueReminders($this->instant('2026-08-01 08:26:00')));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::BREAK_REMINDERS, [
            'break_id' => $break->id,
            'reminder_type' => 'before_maximum',
            'due_at' => '2026-08-01 06:25:00+00',
        ]);

        $this->assertDatabaseMissing(NotificationsDatabaseTable::NOTIFICATIONS, [
            'type' => 'time_tracking.break.before_maximum',
        ]);
    }

    public function test_other_work_categories_are_scoped_to_one_team_with_required_comments(): void
    {
        $teamId = $this->createTeam();
        $otherTeamId = $this->createTeam('Other Team');
        $categories = $this->app->make(OtherWorkCategoryStore::class);

        $categories->upsertTeam(
            teamId: $teamId,
            categoryKey: 'field_visit',
            labelPl: 'Wizyta terenowa',
            labelEn: 'Field visit',
            descriptionPl: 'Praca poza aplikacja.',
            descriptionEn: 'Work outside the application.',
            requiresComment: true,
        );
        $categories->upsertTeam(
            teamId: $otherTeamId,
            categoryKey: 'skip_for_team',
            labelPl: 'Inny zespol',
            labelEn: 'Other team',
            descriptionPl: null,
            descriptionEn: null,
            requiresComment: false,
        );

        $active = $categories->activeForTeam($teamId);

        self::assertCount(1, $active);
        self::assertSame(['field_visit'], array_map(static fn (OtherWorkCategory $category): string => $category->categoryKey, $active));
        self::assertTrue($active[0]->requiresComment);
        self::assertSame('Wizyta terenowa', $active[0]->labelPl);
        self::assertSame('Field visit', $active[0]->labelEn);

        $categories->deactivateTeam($teamId, 'field_visit');

        self::assertSame([], array_map(static fn (OtherWorkCategory $category): string => $category->categoryKey, $categories->activeForTeam($teamId)));
    }

    public function test_other_work_session_defaults_to_manager_review_and_closes_with_exact_seconds(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $workSessions = $this->app->make(WorkSessionCoordinator::class);
        $otherWork = $this->app->make(OtherWorkSessionCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);
        $workSessions->synchronizeActiveTeam($userId, $teamId, 'session-a', $this->instant('2026-08-01 08:00:00'));

        $active = $otherWork->start($userId, null, 'Visit outside Atlas.', $this->instant('2026-08-01 08:10:00'));

        self::assertSame($userId, $active->userId);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::OTHER_WORK, [
            'user_id' => $userId,
            'approval_status' => 'pending',
            'requires_manager_review' => true,
            'ended_at' => null,
        ]);

        $otherWork->end($userId, $this->instant('2026-08-01 08:40:00'), 'Returned.');

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::OTHER_WORK, [
            'user_id' => $userId,
            'closure_reason' => 'normal',
            'exact_seconds' => 1800,
            'approval_status' => 'pending',
            'requires_manager_review' => true,
        ]);
    }

    public function test_other_work_category_auto_approval_can_be_moved_to_under_review(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $categories = $this->app->make(OtherWorkCategoryStore::class);
        $workSessions = $this->app->make(WorkSessionCoordinator::class);
        $otherWork = $this->app->make(OtherWorkSessionCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);
        $categories->upsertTeam(
            teamId: $teamId,
            categoryKey: 'trusted_call',
            labelPl: 'Telefon',
            labelEn: 'Call',
            descriptionPl: null,
            descriptionEn: null,
            requiresComment: false,
            autoApprovalEnabled: true,
        );
        $workSessions->synchronizeActiveTeam($userId, $teamId, 'session-a', $this->instant('2026-08-01 08:00:00'));

        $otherWork->start($userId, 'trusted_call', 'Call outside Atlas.', $this->instant('2026-08-01 08:10:00'));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::OTHER_WORK, [
            'user_id' => $userId,
            'category_key' => 'trusted_call',
            'approval_status' => 'approved',
            'requires_manager_review' => false,
        ]);

        $otherWork->moveActiveToUnderReview($userId, 'Manager requested verification.');

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::OTHER_WORK, [
            'user_id' => $userId,
            'approval_status' => 'under_review',
            'requires_manager_review' => true,
            'end_note' => 'Manager requested verification.',
        ]);
    }

    public function test_maintenance_interrupts_active_break_and_other_work_and_tracks_return_grace(): void
    {
        $breakUserId = $this->createUser();
        $otherWorkUserId = $this->createUser();
        $loggedOutUserId = $this->createUser();
        $teamId = $this->createTeam();
        $breakAssignmentId = $this->createAssignment($breakUserId, $teamId);
        $otherWorkAssignmentId = $this->createAssignment($otherWorkUserId, $teamId);
        $loggedOutAssignmentId = $this->createAssignment($loggedOutUserId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $workSessions = $this->app->make(WorkSessionCoordinator::class);
        $breaks = $this->app->make(BreakSessionCoordinator::class);
        $otherWork = $this->app->make(OtherWorkSessionCoordinator::class);
        $maintenance = $this->app->make(MaintenanceCoordinator::class);

        $settings->setEnabledForAssignment($breakAssignmentId, true);
        $settings->setEnabledForAssignment($otherWorkAssignmentId, true);
        $settings->setEnabledForAssignment($loggedOutAssignmentId, true);
        $workSessions->synchronizeActiveTeam($breakUserId, $teamId, 'session-break', $this->instant('2026-08-01 08:00:00'));
        $workSessions->synchronizeActiveTeam($otherWorkUserId, $teamId, 'session-other', $this->instant('2026-08-01 08:00:00'));
        $workSessions->synchronizeActiveTeam($loggedOutUserId, $teamId, 'session-closed', $this->instant('2026-08-01 08:00:00'));
        $workSessions->endForLogout($loggedOutUserId, $this->instant('2026-08-01 08:05:00'));
        $breaks->start($breakUserId, $this->instant('2026-08-01 08:10:00'));
        $otherWork->start($otherWorkUserId, null, 'Outside maintenance-sensitive work.', $this->instant('2026-08-01 08:12:00'));

        $window = $maintenance->startEmergency($this->instant('2026-08-01 08:30:00'), 'Database maintenance.');

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::MAINTENANCE_WINDOWS, [
            'id' => $window->id,
            'kind' => 'emergency',
            'status' => 'active',
        ]);
        self::assertSame(2, DB::table(TimeTrackingDatabaseTable::MAINTENANCE_AFFECTED_SESSIONS)->where('maintenance_window_id', $window->id)->count());
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::BREAKS, [
            'user_id' => $breakUserId,
            'closure_reason' => 'forced',
            'requires_manager_review' => true,
            'exact_seconds' => 1200,
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::OTHER_WORK, [
            'user_id' => $otherWorkUserId,
            'closure_reason' => 'forced',
            'approval_status' => 'under_review',
            'requires_manager_review' => true,
            'exact_seconds' => 1080,
        ]);

        $maintenance->complete($window->id, $this->instant('2026-08-01 08:45:00'));

        self::assertTrue($maintenance->recordReturn($breakUserId, $this->instant('2026-08-01 08:54:59')));
        self::assertFalse($maintenance->recordReturn($otherWorkUserId, $this->instant('2026-08-01 08:56:00')));
    }

    public function test_scheduled_maintenance_can_be_activated_later(): void
    {
        $maintenance = $this->app->make(MaintenanceCoordinator::class);

        $window = $maintenance->schedule($this->instant('2026-08-01 22:00:00'), 'Planned upgrade.');

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::MAINTENANCE_WINDOWS, [
            'id' => $window->id,
            'kind' => 'scheduled',
            'status' => 'scheduled',
        ]);

        $maintenance->activateScheduled($window->id, $this->instant('2026-08-01 22:00:00'));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::MAINTENANCE_WINDOWS, [
            'id' => $window->id,
            'status' => 'active',
        ]);
    }

    public function test_backend_inactivity_ends_counted_work_at_warning_start(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $workSessions = $this->app->make(WorkSessionCoordinator::class);
        $inactivity = $this->app->make(InactivityCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);
        $workSessions->synchronizeActiveTeam($userId, $teamId, 'session-a', $this->instant('2026-08-01 08:00:00'));

        $decision = $inactivity->evaluate(
            userId: $userId,
            lastActivityAt: $this->instant('2026-08-01 08:10:00'),
            observedAt: $this->instant('2026-08-01 08:15:20'),
        );

        self::assertTrue($decision->workEnded);
        self::assertSame(300, $decision->warningStartsAt->getTimestamp() - $this->instant('2026-08-01 08:10:00')->getTimestamp());
        self::assertSame(30, $decision->warningEndsAt->getTimestamp() - $decision->warningStartsAt->getTimestamp());
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'closure_reason' => 'inactivity',
            'exact_seconds' => 900,
        ]);
    }

    public function test_inactivity_does_not_close_work_during_other_work_lock(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $assignmentId = $this->createAssignment($userId, $teamId);
        $settings = $this->app->make(UserTeamTrackingSettings::class);
        $workSessions = $this->app->make(WorkSessionCoordinator::class);
        $otherWork = $this->app->make(OtherWorkSessionCoordinator::class);
        $inactivity = $this->app->make(InactivityCoordinator::class);

        $settings->setEnabledForAssignment($assignmentId, true);
        $workSessions->synchronizeActiveTeam($userId, $teamId, 'session-a', $this->instant('2026-08-01 08:00:00'));
        $otherWork->start($userId, null, 'Outside work.', $this->instant('2026-08-01 08:05:00'));

        $decision = $inactivity->closeAfterBrowserHeartbeatLoss(
            userId: $userId,
            lastHeartbeatAt: $this->instant('2026-08-01 08:10:00'),
            detectedAt: $this->instant('2026-08-01 08:20:00'),
        );

        self::assertFalse($decision->workEnded);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::WORK_SESSIONS, [
            'user_id' => $userId,
            'ended_at' => null,
        ]);
    }

    public function test_correction_requests_preserve_description_and_history(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $corrections = $this->app->make(CorrectionRequestCoordinator::class);

        $request = $corrections->requestDescriptive(
            userId: $userId,
            teamId: $teamId,
            workSessionId: null,
            description: 'Forgot to explain field work.',
            requestedAt: $this->instant('2026-08-01 08:00:00'),
        );

        self::assertSame('pending', $request->status);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_REQUESTS, [
            'id' => $request->id,
            'source_type' => null,
            'source_id' => null,
            'request_type' => 'descriptive',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_HISTORY, [
            'correction_request_id' => $request->id,
            'actor_user_id' => $userId,
            'action' => 'requested',
        ]);
    }

    public function test_exact_change_can_be_cancelled_while_pending(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $workSessionId = $this->createWorkSession($userId, $teamId);
        $corrections = $this->app->make(CorrectionRequestCoordinator::class);
        $original = new ExactTimeChange($this->instant('2026-08-01 08:00:00'), $this->instant('2026-08-01 09:00:00'), 3600);
        $proposed = new ExactTimeChange($this->instant('2026-08-01 08:00:00'), $this->instant('2026-08-01 09:30:00'), 5400);

        $request = $corrections->requestExactChange($userId, $teamId, $workSessionId, 'Missed field call.', $original, $proposed, $this->instant('2026-08-01 10:00:00'));
        $cancelled = $corrections->cancelPending($request->id, $userId, 'I created a better request.', $this->instant('2026-08-01 10:05:00'));

        self::assertTrue($cancelled);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_REQUESTS, [
            'id' => $request->id,
            'source_type' => 'work_session',
            'source_id' => $workSessionId,
            'status' => 'cancelled',
            'cancellation_reason' => 'I created a better request.',
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_PROPOSALS, [
            'correction_request_id' => $request->id,
            'original_exact_seconds' => 3600,
            'proposed_exact_seconds' => 5400,
        ]);
    }

    public function test_first_manager_decision_wins_and_partial_correction_preserves_final_values(): void
    {
        $userId = $this->createUser();
        $managerId = $this->createUser();
        $otherManagerId = $this->createUser();
        $teamId = $this->createTeam();
        $corrections = $this->app->make(CorrectionRequestCoordinator::class);
        $request = $corrections->requestDescriptive($userId, $teamId, null, 'Needs partial correction.', $this->instant('2026-08-01 08:00:00'));
        $final = new ExactTimeChange($this->instant('2026-08-01 08:00:00'), $this->instant('2026-08-01 08:45:00'), 2700);

        self::assertTrue($corrections->correctPending($request->id, $managerId, $final, 'Approved 45 minutes.', $this->instant('2026-08-01 09:00:00')));
        self::assertFalse($corrections->rejectPending($request->id, $otherManagerId, 'Too late.', $this->instant('2026-08-01 09:01:00')));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_REQUESTS, [
            'id' => $request->id,
            'status' => 'corrected',
            'decided_by_user_id' => $managerId,
            'decision_reason' => 'Approved 45 minutes.',
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_PROPOSALS, [
            'correction_request_id' => $request->id,
            'final_exact_seconds' => 2700,
        ]);
        self::assertSame(2, DB::table(TimeTrackingDatabaseTable::CORRECTION_HISTORY)->where('correction_request_id', $request->id)->count());
    }

    public function test_manual_head_manager_entry_is_final_correction_with_visible_marker_type(): void
    {
        $userId = $this->createUser();
        $managerId = $this->createUser();
        $teamId = $this->createTeam();
        $corrections = $this->app->make(CorrectionRequestCoordinator::class);
        $final = new ExactTimeChange($this->instant('2026-08-01 12:00:00'), $this->instant('2026-08-01 12:30:00'), 1800);

        $request = $corrections->createManualEntry($managerId, $userId, $teamId, $final, 'Head manager manual entry.', $this->instant('2026-08-01 13:00:00'));

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_REQUESTS, [
            'id' => $request->id,
            'source_type' => null,
            'source_id' => null,
            'request_type' => 'manual_entry',
            'status' => 'corrected',
            'decided_by_user_id' => $managerId,
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_HISTORY, [
            'correction_request_id' => $request->id,
            'actor_user_id' => $managerId,
            'action' => 'manual_entry',
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_PROPOSALS, [
            'correction_request_id' => $request->id,
            'final_exact_seconds' => 1800,
        ]);
    }

    public function test_settlement_period_uses_default_tenth_to_ninth_bounds_and_closes_due_periods(): void
    {
        $settlements = $this->app->make(SettlementPeriodCoordinator::class);

        $period = $settlements->periodFor($this->instant('2026-08-01 12:00:00'));

        self::assertSame('2026-07-10', $period->startsOn->format('Y-m-d'));
        self::assertSame('2026-08-09', $period->endsOn->format('Y-m-d'));
        self::assertSame('open', $period->status);

        $closed = $settlements->closeDuePeriods($this->instant('2026-08-10 00:01:00'));

        self::assertSame(1, $closed);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::SETTLEMENT_PERIODS, [
            'id' => $period->id,
            'status' => 'closed',
        ]);
    }

    public function test_settlement_period_start_day_is_configurable(): void
    {
        $settlements = $this->app->make(SettlementPeriodCoordinator::class);

        $settlements->setStartDay(5);
        $period = $settlements->periodFor($this->instant('2026-08-04 12:00:00'));

        self::assertSame('2026-07-05', $period->startsOn->format('Y-m-d'));
        self::assertSame('2026-08-04', $period->endsOn->format('Y-m-d'));
    }

    public function test_closed_period_high_risk_admin_override_creates_final_correction_with_preview_evidence(): void
    {
        $userId = $this->createUser();
        $adminId = $this->createUser();
        $teamId = $this->createTeam();
        $corrections = $this->app->make(CorrectionRequestCoordinator::class);
        $original = new ExactTimeChange($this->instant('2026-07-10 08:00:00'), $this->instant('2026-07-10 09:00:00'), 3600);
        $final = new ExactTimeChange($this->instant('2026-07-10 08:00:00'), $this->instant('2026-07-10 09:30:00'), 5400);

        $request = $corrections->createClosedPeriodCorrection(
            actorUserId: $adminId,
            userId: $userId,
            teamId: $teamId,
            original: $original,
            final: $final,
            authorization: new ClosedPeriodOverrideAuthorization(
                actorScope: 'admin',
                adminModeConfirmed: true,
                highRiskReauthenticated: true,
                mfaConfirmed: true,
                beforeAfterPreviewConfirmed: true,
                reason: 'No eligible head manager exists.',
                authorizedAt: $this->instant('2026-08-15 12:00:00'),
            ),
        );

        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_REQUESTS, [
            'id' => $request->id,
            'request_type' => 'closed_period_override',
            'status' => 'corrected',
            'decided_by_user_id' => $adminId,
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CLOSED_PERIOD_OVERRIDES, [
            'correction_request_id' => $request->id,
            'actor_user_id' => $adminId,
            'actor_scope' => 'admin',
            'admin_mode_confirmed' => true,
            'high_risk_reauthenticated' => true,
            'mfa_confirmed' => true,
            'before_after_preview_confirmed' => true,
        ]);
        $this->assertDatabaseHas(TimeTrackingDatabaseTable::CORRECTION_PROPOSALS, [
            'correction_request_id' => $request->id,
            'original_exact_seconds' => 3600,
            'final_exact_seconds' => 5400,
        ]);
    }

    public function test_closed_period_override_requires_high_risk_confirmations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $corrections = $this->app->make(CorrectionRequestCoordinator::class);
        $change = new ExactTimeChange($this->instant('2026-07-10 08:00:00'), $this->instant('2026-07-10 09:00:00'), 3600);

        $corrections->createClosedPeriodCorrection(
            actorUserId: $this->createUser(),
            userId: $this->createUser(),
            teamId: $this->createTeam(),
            original: $change,
            final: $change,
            authorization: new ClosedPeriodOverrideAuthorization(
                actorScope: 'admin',
                adminModeConfirmed: true,
                highRiskReauthenticated: false,
                mfaConfirmed: false,
                beforeAfterPreviewConfirmed: true,
                reason: 'Missing high-risk checks.',
                authorizedAt: $this->instant('2026-08-15 12:00:00'),
            ),
        );
    }

    public function test_user_report_merges_time_records_and_applies_filters(): void
    {
        $userId = $this->createUser();
        $otherUserId = $this->createUser();
        $teamId = $this->createTeam();
        $otherTeamId = $this->createTeam('Other Time Tracking Team');
        $workSessionId = $this->createWorkSession($userId, $teamId);

        DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)
            ->where('id', $workSessionId)
            ->update([
                'started_at' => '2026-08-01 08:00:00+00',
                'ended_at' => '2026-08-01 10:00:00+00',
                'exact_seconds' => 7200,
                'closure_reason' => 'logout',
            ]);
        DB::table(TimeTrackingDatabaseTable::BREAKS)->insert([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $userId,
            'team_id' => $teamId,
            'started_at' => '2026-08-01 09:00:00+00',
            'ended_at' => '2026-08-01 09:15:00+00',
            'exact_seconds' => 900,
            'closure_reason' => 'user_returned',
            'requires_manager_review' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(TimeTrackingDatabaseTable::OTHER_WORK)->insert([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $userId,
            'team_id' => $teamId,
            'category_key' => 'court_call',
            'description' => 'Court call.',
            'approval_status' => 'approved',
            'started_at' => '2026-08-01 10:15:00+00',
            'ended_at' => '2026-08-01 10:45:00+00',
            'exact_seconds' => 1800,
            'closure_reason' => 'completed',
            'requires_manager_review' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(TimeTrackingDatabaseTable::CORRECTION_REQUESTS)->insert([
            'public_id' => (string) Str::ulid(),
            'user_id' => $userId,
            'team_id' => $teamId,
            'work_session_id' => $workSessionId,
            'status' => 'pending',
            'request_type' => 'exact_change',
            'description' => 'Forgot one action.',
            'requested_at' => '2026-08-01 11:00:00+00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insert([
            'public_id' => (string) Str::ulid(),
            'user_id' => $otherUserId,
            'team_id' => $teamId,
            'laravel_session_id' => 'session-other-user',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => '2026-08-01 09:00:00+00',
            'exact_seconds' => 3600,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insert([
            'public_id' => (string) Str::ulid(),
            'user_id' => $userId,
            'team_id' => $otherTeamId,
            'laravel_session_id' => 'session-other-team',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => '2026-08-01 09:00:00+00',
            'exact_seconds' => 3600,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = $this->app->make(UserTimeReportService::class)->forRequest(
            Request::create('/user/work-time', 'GET', [
                'range' => 'custom',
                'from' => '2026-08-01',
                'to' => '2026-08-01',
            ]),
            $userId,
            $teamId,
        );

        self::assertCount(4, $report->rows);
        self::assertSame(9000, $report->summary['totalSeconds']);
        self::assertSame(7200, $report->summary['workSeconds']);
        self::assertSame(900, $report->summary['breakSeconds']);
        self::assertSame(1800, $report->summary['otherWorkSeconds']);
        self::assertSame(1, $report->summary['corrections']);
        self::assertSame(1, $report->summary['pending']);

        $filtered = $this->app->make(UserTimeReportService::class)->forRequest(
            Request::create('/user/work-time', 'GET', [
                'range' => 'custom',
                'from' => '2026-08-01',
                'to' => '2026-08-01',
                'type' => 'other_work',
                'status' => 'approved',
                'module' => 'court_call',
            ]),
            $userId,
            $teamId,
        );

        self::assertCount(1, $filtered->rows);
        self::assertSame('other_work', $filtered->rows[0]['type']);
        self::assertSame('approved', $filtered->rows[0]['status']);
        self::assertSame('court_call', $filtered->rows[0]['context']);
        self::assertSame(1800, $filtered->summary['otherWorkSeconds']);
    }

    public function test_user_work_time_daily_summary_separates_review_breaks_and_maintenance(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $workSessionId = $this->createWorkSession($userId, $teamId);

        DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)
            ->where('id', $workSessionId)
            ->update([
                'started_at' => '2026-08-01 08:00:00+00',
                'ended_at' => '2026-08-01 15:30:00+00',
                'exact_seconds' => 27000,
                'closure_reason' => 'logout',
            ]);
        DB::table(TimeTrackingDatabaseTable::BREAKS)->insert([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $userId,
            'team_id' => $teamId,
            'started_at' => '2026-08-01 10:10:00+00',
            'ended_at' => '2026-08-01 10:28:00+00',
            'exact_seconds' => 1080,
            'closure_reason' => 'user_returned',
            'requires_manager_review' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(TimeTrackingDatabaseTable::BREAKS)->insert([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $userId,
            'team_id' => $teamId,
            'started_at' => '2026-08-01 11:00:00+00',
            'ended_at' => '2026-08-01 11:05:00+00',
            'exact_seconds' => 300,
            'closure_reason' => 'forced',
            'requires_manager_review' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $maintenanceId = (int) DB::table(TimeTrackingDatabaseTable::MAINTENANCE_WINDOWS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'kind' => 'scheduled',
            'status' => 'completed',
            'scheduled_start_at' => '2026-08-01 12:00:00+00',
            'started_at' => '2026-08-01 12:00:00+00',
            'completed_at' => '2026-08-01 12:18:00+00',
            'return_grace_seconds' => 600,
            'reason' => 'Test maintenance.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(TimeTrackingDatabaseTable::MAINTENANCE_AFFECTED_SESSIONS)->insert([
            'public_id' => (string) Str::ulid(),
            'maintenance_window_id' => $maintenanceId,
            'work_session_id' => $workSessionId,
            'user_id' => $userId,
            'team_id' => $teamId,
            'interrupted_at' => '2026-08-01 12:00:00+00',
            'return_deadline_at' => '2026-08-01 12:28:00+00',
            'returned_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = $this->app->make(UserTimeReportService::class)->workTimeForRequest(
            Request::create('/user/work-time', 'GET', [
                'range' => 'custom',
                'from' => '2026-08-01',
                'to' => '2026-08-01',
            ]),
            $userId,
            $teamId,
        );

        self::assertCount(1, $report->dailyRows);
        self::assertSame(27000 + 1080, $report->dailyRows[0]['countedSeconds']);
        self::assertSame(27000, $report->dailyRows[0]['workSeconds']);
        self::assertSame(1080, $report->dailyRows[0]['breakSeconds']);
        self::assertSame(300, $report->dailyRows[0]['technicalBreakSeconds']);
        self::assertSame(1080, $report->dailyRows[0]['maintenanceSeconds']);
        self::assertSame(1080, $report->summary['maintenanceSeconds']);
    }

    public function test_user_report_compares_current_range_with_previous_equal_range(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();

        $currentWorkSessionId = (int) DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $userId,
            'team_id' => $teamId,
            'laravel_session_id' => 'session-current',
            'started_at' => '2026-08-01 08:00:00+00',
            'ended_at' => '2026-08-01 10:00:00+00',
            'exact_seconds' => 7200,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $previousWorkSessionId = (int) DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $userId,
            'team_id' => $teamId,
            'laravel_session_id' => 'session-previous',
            'started_at' => '2026-07-31 08:00:00+00',
            'ended_at' => '2026-07-31 09:00:00+00',
            'exact_seconds' => 3600,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(TimeTrackingDatabaseTable::BREAKS)->insert([
            [
                'public_id' => (string) Str::ulid(),
                'work_session_id' => $currentWorkSessionId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'started_at' => '2026-08-01 10:00:00+00',
                'ended_at' => '2026-08-01 10:15:00+00',
                'exact_seconds' => 900,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'public_id' => (string) Str::ulid(),
                'work_session_id' => $previousWorkSessionId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'started_at' => '2026-07-31 09:00:00+00',
                'ended_at' => '2026-07-31 09:30:00+00',
                'exact_seconds' => 1800,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $report = $this->app->make(UserTimeReportService::class)->forRequest(
            Request::create('/user/work-time', 'GET', [
                'range' => 'custom',
                'from' => '2026-08-01',
                'to' => '2026-08-01',
                'compare' => 'previous',
            ]),
            $userId,
            $teamId,
        );

        self::assertNotNull($report->comparison);
        self::assertSame('2026-08-01 - 2026-08-01', $report->comparison['rangeLabel']);
        self::assertSame('2026-07-31 - 2026-07-31', $report->comparison['previousRangeLabel']);
        self::assertSame([
            'metric' => 'work',
            'currentSeconds' => 7200,
            'previousSeconds' => 3600,
            'deltaSeconds' => 3600,
            'percentDelta' => 100.0,
        ], $report->comparison['metrics'][0]);
        self::assertSame([
            'metric' => 'break',
            'currentSeconds' => 900,
            'previousSeconds' => 1800,
            'deltaSeconds' => -900,
            'percentDelta' => -50.0,
        ], $report->comparison['metrics'][1]);
    }

    public function test_break_and_correction_operations_write_audit_events(): void
    {
        $userId = $this->createUser();
        $teamId = $this->createTeam();
        $workSessionId = $this->createWorkSession($userId, $teamId);

        $this->app->make(BreakSessionCoordinator::class)->start($userId, $this->instant('2026-08-01 10:00:00'));
        $this->app->make(CorrectionRequestCoordinator::class)->requestDescriptive(
            $userId,
            $teamId,
            $workSessionId,
            'Forgot a short manual note.',
            $this->instant('2026-08-01 11:00:00'),
        );

        $this->assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, [
            'module' => 'time_tracking',
            'action' => 'time_tracking.break_started',
            'result' => 'succeeded',
        ]);
        $this->assertDatabaseHas(NotificationsDatabaseTable::REALTIME_EVENTS, [
            'topic' => 'time-tracking',
            'event_type' => 'time_tracking.status.changed',
        ]);
        $this->assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, [
            'module' => 'time_tracking',
            'action' => 'time_tracking.correction_requested',
            'result' => 'succeeded',
        ]);
    }

    private function createUser(): int
    {
        return (int) DB::table(IdentityDatabaseTable::USERS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'name' => 'Time Tracking User',
            'email' => sprintf('%s@example.test', Str::lower((string) Str::ulid())),
            'password' => 'not-a-real-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTeam(string $name = 'Time Tracking Team'): int
    {
        return (int) DB::table(TeamsDatabaseTable::TEAMS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAssignment(int $userId, int $teamId, ?string $validTo = null): int
    {
        return (int) DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insertGetId([
            'team_id' => $teamId,
            'user_id' => $userId,
            'is_head_manager' => false,
            'valid_from' => '2026-08-01 08:00:00+00',
            'valid_to' => $validTo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createWorkSession(int $userId, int $teamId): int
    {
        return (int) DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $userId,
            'team_id' => $teamId,
            'laravel_session_id' => 'session-a',
            'started_at' => '2026-08-01 08:00:00+00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function requestForRoute(string $routeName, int $userId): Request
    {
        $request = Request::create('/'.$routeName, 'POST');
        $route = new Route(['POST'], '/'.$routeName, []);
        $route->name($routeName);
        $user = new stdClass;
        $user->id = $userId;
        $request->setRouteResolver(static fn (): Route => $route);
        $request->setUserResolver(static fn (): stdClass => $user);

        return $request;
    }

    private function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('Europe/Warsaw'));
    }
}
