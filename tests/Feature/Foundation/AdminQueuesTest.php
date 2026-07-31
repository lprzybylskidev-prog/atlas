<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminQueuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_browse_failed_jobs(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $this->insertFailedJob('11111111-1111-4111-8111-111111111111', 'emails');

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/queues')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Queues/Index')
                ->where('summary.failedCount', 1)
                ->where('summary.handledCount', 0)
                ->where('table.key', 'admin.queues.failed-jobs')
                ->where('table.state.filters.queue', 'all')
                ->where('table.state.filters.handling', 'needs_attention')
                ->where('queueOperations.totalFailedJobs', 1)
                ->where('queueOperations.totalHandledJobs', 0)
                ->where('queueOperations.knownQueues.1.queue', 'emails')
                ->where('queueOperations.knownQueues.1.configured', false)
                ->where('queueOperations.knownQueues.1.failedJobs', 1)
                ->where('queueOperations.knownQueues.1.handledJobs', 0)
                ->where('jobs.0.uuid', '11111111-1111-4111-8111-111111111111')
                ->where('jobs.0.queue', 'emails')
                ->where('jobs.0.displayName', 'Demo failed job')
                ->where('jobs.0.jobClass', 'App\\Jobs\\DemoFailedJob')
                ->where('jobs.0.handlingStatus', 'needs_attention')
                ->where('jobDetails.0.payload', fn (string $payload): bool => str_contains($payload, 'DemoFailedJob'))
                ->where('filterOptions.queues.0', 'emails')
            );

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/queues?queue=emails')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Queues/Index')
                ->where('table.state.filters.queue', 'emails')
                ->has('jobs', 1)
            );
    }

    public function test_admin_can_mark_failed_jobs_as_handled_and_hide_them_from_attention_views(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $handledUuid = '77777777-7777-4777-8777-777777777777';
        $pendingUuid = '88888888-8888-4888-8888-888888888888';
        $this->insertFailedJob($handledUuid, 'emails');
        $this->insertFailedJob($pendingUuid, 'imports');

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/queues/failed-jobs/acknowledge', [
                'uuids' => [$handledUuid],
            ])
            ->assertRedirect('/admin/queues')
            ->assertSessionHas('flash.messages.0.key', 'flash.queues.acknowledge_single')
            ->assertSessionMissing('success');

        self::assertDatabaseHas(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS, [
            'failed_job_uuid' => $handledUuid,
            'acknowledged_by_user_id' => $admin->id,
        ]);
        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'authorization',
            'action' => 'queue.failed_job_acknowledge',
            'result' => 'succeeded',
            'source' => 'admin',
            'target_type' => 'failed_job',
            'is_security' => true,
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/queues')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.failedCount', 1)
                ->where('summary.handledCount', 1)
                ->where('table.state.filters.handling', 'needs_attention')
                ->has('jobs', 1)
                ->where('jobs.0.uuid', $pendingUuid)
            );

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/queues?handling=handled')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('table.state.filters.handling', 'handled')
                ->has('jobs', 1)
                ->where('jobs.0.uuid', $handledUuid)
                ->where('jobs.0.handlingStatus', 'handled')
            );
    }

    public function test_admin_dashboard_exposes_failed_job_summary(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $this->insertFailedJob('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'emails');
        $this->insertFailedJob('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'imports');
        DB::table(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS)->insert([
            'public_id' => (string) Str::ulid(),
            'failed_job_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'acknowledged_by_user_id' => $admin->id,
            'reason' => null,
            'acknowledged_at' => now(),
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
            ->get('/admin/system-status/failed-jobs')
            ->assertOk()
            ->assertJsonPath('data.status', 'degraded')
            ->assertJsonPath('data.failedCount', 1)
            ->assertJsonPath('data.queueCount', 1);
    }

    public function test_admin_can_retry_one_failed_job_and_audit_the_action(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $uuid = '22222222-2222-4222-8222-222222222222';
        $this->insertFailedJob($uuid, 'default');

        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', ['id' => [$uuid]])
            ->andReturn(0);

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->post('/admin/queues/failed-jobs/retry', [
                'uuids' => [$uuid],
            ])
            ->assertRedirect('/admin/queues')
            ->assertSessionHas('flash.messages.0.key', 'flash.queues.retry_single_queued')
            ->assertSessionMissing('success');

        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'authorization',
            'action' => 'queue.failed_job_retry',
            'result' => 'succeeded',
            'source' => 'admin',
            'target_type' => 'failed_job',
            'is_security' => true,
        ]);
        self::assertDatabaseHas(DatabaseTable::AUDIT_SECURITY_EVENTS, [
            'category' => 'queue_operations',
            'action' => 'queue.failed_job_retry',
            'result' => 'succeeded',
        ]);
    }

    public function test_mass_retry_requires_typed_confirmation(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $this->insertFailedJob('33333333-3333-4333-8333-333333333333', 'default');
        $this->insertFailedJob('44444444-4444-4444-8444-444444444444', 'default');

        Artisan::shouldReceive('call')->never();

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->post('/admin/queues/failed-jobs/retry', [
                'uuids' => [
                    '33333333-3333-4333-8333-333333333333',
                    '44444444-4444-4444-8444-444444444444',
                ],
            ])
            ->assertRedirect('/admin/queues')
            ->assertSessionHas('flash.messages.0.key', 'flash.queues.retry_typed_confirmation_required')
            ->assertSessionMissing('error');
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

    private function insertFailedJob(string $uuid, string $queue): void
    {
        DB::table(DatabaseTable::FAILED_JOBS)->insert([
            'uuid' => $uuid,
            'connection' => 'redis',
            'queue' => $queue,
            'payload' => json_encode([
                'displayName' => 'Demo failed job',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => [
                    'commandName' => 'App\\Jobs\\DemoFailedJob',
                ],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: Demo queue failure'.PHP_EOL.'#0 /workspace/app/Demo.php(10): demo()',
            'failed_at' => now(),
        ]);
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
