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
                ->where('jobs.0.uuid', '11111111-1111-4111-8111-111111111111')
                ->where('jobs.0.queue', 'emails')
                ->where('jobs.0.displayName', 'Demo failed job')
                ->where('jobs.0.jobClass', 'App\\Jobs\\DemoFailedJob')
            );
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
            ->assertSessionHas('success', 'Failed job was queued for retry.');

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
            ->assertSessionHas('error', 'Mass retry requires typed confirmation.');
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
