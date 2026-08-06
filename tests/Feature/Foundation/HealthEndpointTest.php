<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Health\Application\Readiness\Contracts\ReadinessChecker;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Observability\SchedulerHeartbeatMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_returns_minimal_public_payload(): void
    {
        $this->get('/health/live')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }

    public function test_readiness_endpoint_keeps_public_payload_minimal_and_fails_for_missing_scheduler_heartbeat(): void
    {
        $this->useNonRedisRuntimeForDeterministicReadiness();

        $this->get('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'unhealthy')
            ->assertJsonPath('release.version', '0.1.0-dev')
            ->assertJsonPath('release.id', 'local')
            ->assertJsonPath('blocking.failed', 1)
            ->assertJsonMissingPath('checks');
    }

    public function test_readiness_endpoint_allows_degraded_optional_dependencies_without_failing_deploy_health(): void
    {
        $this->useNonRedisRuntimeForDeterministicReadiness();
        $this->app->make(SchedulerHeartbeatMonitor::class)->markHealthy(14);

        $this->get('/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('release.version', '0.1.0-dev')
            ->assertJsonPath('release.id', 'local')
            ->assertJsonPath('blocking.failed', 0)
            ->assertJsonPath('degraded.failed', 2)
            ->assertJsonMissingPath('checks');
    }

    public function test_admin_system_status_exposes_detailed_readiness_card(): void
    {
        $this->useNonRedisRuntimeForDeterministicReadiness();
        $this->app->make(SchedulerHeartbeatMonitor::class)->markHealthy(9);
        [$admin, $team] = $this->adminWithTeam();

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
                ->where('availability.1.elementKey', 'admin.system-status.readiness')
                ->where('availability.1.reason', 'available')
            );

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/system-status/readiness')
            ->assertOk()
            ->assertJsonPath('data.status', 'degraded')
            ->assertJsonPath('data.blockingFailed', 0)
            ->assertJsonPath('data.degradedFailed', 2)
            ->assertJsonPath('data.checks.0.key', 'critical-configuration')
            ->assertJsonPath('data.checks.1.key', 'postgresql')
            ->assertJsonPath('data.checks.5.key', 'scheduler')
            ->assertJsonPath('data.checks.6.key', 'meilisearch')
            ->assertJsonPath('data.checks.7.key', 'clamav')
            ->assertJsonPath('data.checks.8.key', 'chromium-pdf');
    }

    public function test_readiness_blocks_when_clamav_is_configured_as_critical_without_daemon(): void
    {
        $this->useNonRedisRuntimeForDeterministicReadiness();
        Config::set('atlas.operations.health.clamav.critical', true);
        Config::set('atlas.operations.health.clamav.host', null);
        $this->app->make(SchedulerHeartbeatMonitor::class)->markHealthy(14);

        $this->get('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'unhealthy')
            ->assertJsonPath('blocking.failed', 1);
    }

    public function test_readiness_blocks_when_files_are_deployed_in_production_without_clamav(): void
    {
        $this->useNonRedisRuntimeForDeterministicReadiness();
        $this->app->detectEnvironment(fn (): string => 'production');
        Config::set('atlas.operations.health.clamav.critical', false);
        Config::set('atlas.operations.health.clamav.host', null);
        $this->app->make(SchedulerHeartbeatMonitor::class)->markHealthy(14);

        $this->get('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'unhealthy')
            ->assertJsonPath('blocking.failed', 1);
    }

    public function test_readiness_blocks_when_chromium_pdf_renderer_is_configured_as_critical_without_binary(): void
    {
        $this->useNonRedisRuntimeForDeterministicReadiness();
        Config::set('atlas.operations.health.chromium.critical', true);
        Config::set('atlas.operations.health.chromium.binary', null);
        $this->app->make(SchedulerHeartbeatMonitor::class)->markHealthy(14);

        $this->get('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'unhealthy')
            ->assertJsonPath('blocking.failed', 1);
    }

    public function test_readiness_accepts_configured_chromium_pdf_renderer_binary(): void
    {
        $this->useNonRedisRuntimeForDeterministicReadiness();
        $binary = storage_path('framework/testing-chromium');

        try {
            file_put_contents($binary, '#!/bin/sh'.PHP_EOL.'exit 0'.PHP_EOL);
            chmod($binary, 0755);

            Config::set('atlas.operations.health.chromium.binary', $binary);
            $this->app->make(SchedulerHeartbeatMonitor::class)->markHealthy(14);

            $report = $this->app->make(ReadinessChecker::class)->check()->toAdminArray();
            $chromium = collect($report['checks'])->firstWhere('key', 'chromium-pdf');

            if (! is_array($chromium)) {
                self::fail('Chromium/PDF readiness check was not reported.');
            }

            self::assertSame('healthy', $chromium['status']);
            self::assertSame('configured', $chromium['metadata']['source']);
        } finally {
            if (is_file($binary)) {
                unlink($binary);
            }
        }
    }

    public function test_admin_system_status_exposes_release_and_last_deploy_metadata(): void
    {
        Config::set('atlas.release.version', '16.2.0');
        Config::set('atlas.release.id', 'release-20260717');
        Config::set('atlas.release.deployed_at', '2026-07-17T12:30:00Z');
        Config::set('atlas.release.deployed_by', 'ops@example.test');
        Config::set('atlas.release.source', 'git:abc123');

        [$admin, $team] = $this->adminWithTeam();

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
                ->where('availability.0.elementKey', 'admin.system-status.release')
                ->where('availability.0.reason', 'available')
            );

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/system-status/release')
            ->assertOk()
            ->assertJsonPath('data.releaseVersion', '16.2.0')
            ->assertJsonPath('data.releaseId', 'release-20260717')
            ->assertJsonPath('data.laravelVersion', app()->version())
            ->assertJsonPath('data.phpVersion', PHP_VERSION)
            ->assertJsonPath('data.timezone', config('app.timezone'))
            ->assertJsonPath('data.deployedAt', '2026-07-17T12:30:00Z')
            ->assertJsonPath('data.deployedBy', 'ops@example.test')
            ->assertJsonPath('data.deploySource', 'git:abc123');
    }

    private function useNonRedisRuntimeForDeterministicReadiness(): void
    {
        Config::set('cache.default', 'array');
        Config::set('queue.default', 'sync');
        Config::set('session.driver', 'array');
        Config::set('scout.meilisearch.host', '');
        Config::set('atlas.operations.health.meilisearch_critical', false);
        Config::set('atlas.operations.health.clamav.critical', false);
        Config::set('atlas.operations.health.clamav.host', null);
        Config::set('atlas.operations.health.chromium.critical', false);
        Config::set('atlas.operations.health.chromium.binary', null);
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

        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);

        return [$user, $team];
    }
}
