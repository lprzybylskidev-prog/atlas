<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationScheduleStatus;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Observability\ModuleActivationScheduleDiagnostics;
use App\Shared\Infrastructure\Observability\SchedulerHeartbeatMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class SchedulerHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_heartbeat_command_records_fresh_status(): void
    {
        self::assertSame(0, Artisan::call('system:scheduler-heartbeat'));

        self::assertDatabaseHas(DatabaseTable::SCHEDULER_HEARTBEATS, [
            'name' => SchedulerHeartbeatMonitor::DEFAULT_NAME,
            'status' => 'healthy',
            'last_error' => null,
        ]);

        $status = $this->app->make(SchedulerHeartbeatMonitor::class)->status();

        self::assertSame('healthy', $status['status']);
        self::assertTrue($status['isFresh']);
        self::assertSame('Scheduler heartbeat is fresh.', $status['description']);
    }

    public function test_scheduler_heartbeat_status_becomes_stale_after_threshold(): void
    {
        $this->app->make(SchedulerHeartbeatMonitor::class)->markHealthy(12);

        DB::table(DatabaseTable::SCHEDULER_HEARTBEATS)
            ->where('name', SchedulerHeartbeatMonitor::DEFAULT_NAME)
            ->update(['last_success_at' => now()->subMinutes(5)]);

        $status = $this->app->make(SchedulerHeartbeatMonitor::class)->status();

        self::assertSame('stale', $status['status']);
        self::assertFalse($status['isFresh']);
        self::assertSame('Scheduler heartbeat is older than the configured freshness threshold.', $status['description']);
    }

    public function test_admin_scheduler_heartbeat_endpoint_exposes_diagnostics(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $this->app->make(SchedulerHeartbeatMonitor::class)->markHealthy(8);

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/system-status/scheduler')
            ->assertOk()
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.lastRuntimeMs', 8)
            ->assertJsonPath('data.staleAfterSeconds', 180);
    }

    public function test_failed_module_activation_schedules_are_audited_and_exposed_as_operational_diagnostics(): void
    {
        DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)->insert([
            'public_id' => '01KFAILED00000000000000000',
            'module_key' => 'missing-module',
            'scope' => ModuleActivationScope::Global->value,
            'team_id' => null,
            'target_enabled' => false,
            'effective_at' => '2000-01-01 00:00:00+00',
            'status' => ModuleActivationScheduleStatus::Scheduled->value,
            'creator_user_id' => null,
            'reason' => 'Operational diagnostics probe.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $applied = $this->app->make(ModuleActivationService::class)->applyDueSchedules();

        self::assertSame(0, $applied);
        self::assertDatabaseHas(DatabaseTable::MODULE_ACTIVATION_SCHEDULES, [
            'public_id' => '01KFAILED00000000000000000',
            'status' => ModuleActivationScheduleStatus::Failed->value,
        ]);
        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'authorization',
            'action' => 'module.schedule_failed',
            'result' => 'failed',
            'source' => 'scheduler',
            'target_type' => 'module_activation_schedule',
            'target_public_id' => '01KFAILED00000000000000000',
            'aggregate_type' => 'module',
            'aggregate_public_id' => 'missing-module',
        ]);

        $status = $this->app->make(ModuleActivationScheduleDiagnostics::class)->status();

        self::assertSame('failed', $status['status']);
        self::assertSame(1, $status['failedCount']);
        self::assertSame('missing-module', $status['latestFailedModule']);
        self::assertSame('01KFAILED00000000000000000', $status['latestFailedPublicId']);
    }

    public function test_admin_system_status_exposes_module_activation_schedule_diagnostics(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)->insert([
            'public_id' => '01KFAILED11111111111111111',
            'module_key' => 'missing-module',
            'scope' => ModuleActivationScope::Global->value,
            'team_id' => null,
            'target_enabled' => false,
            'effective_at' => '2000-01-01 00:00:00+00',
            'status' => ModuleActivationScheduleStatus::Failed->value,
            'creator_user_id' => null,
            'reason' => 'Operational diagnostics probe.',
            'failure_reason' => 'Module [missing-module] is not deployed.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/SystemStatus')
                ->where('availability.2.elementKey', 'admin.system-status.module-activation')
                ->where('availability.2.reason', 'available')
            );

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/system-status/module-activation')
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failedCount', 1)
            ->assertJsonPath('data.scheduledCount', 0)
            ->assertJsonPath('data.latestFailedModule', 'missing-module')
            ->assertJsonPath('data.latestFailureReason', 'Module [missing-module] is not deployed.')
            ->assertJsonPath('data.items.0.module', 'missing-module')
            ->assertJsonPath('data.items.0.status', 'failed');
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function adminWithTeam(): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $role = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();

        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(DatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);

        return [$user, $team];
    }
}
