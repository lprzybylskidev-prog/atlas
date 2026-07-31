<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitRejectionRecorder;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class RateLimitAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_read_only_rate_limit_policies_and_rejection_statistics(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $limiterKey = 'auth.login|user:blocked@example.test|ip:127.0.0.1';

        $this->app->make(RateLimitRejectionRecorder::class)->record('auth.login', $limiterKey, 'req-rate-limit-1');
        $this->app->make(RateLimitRejectionRecorder::class)->record('auth.login', $limiterKey, 'req-rate-limit-2');

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/rate-limits?search=auth.login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/RateLimits/Index')
                ->where('auth.availableAdminRoutes', fn ($routes): bool => self::stringListContains($routes, 'admin.rate-limits.index'))
                ->where('summary.registered', 8)
                ->where('summary.visible', 1)
                ->where('summary.rejections', 2)
                ->where('summary.distinctKeys', 1)
                ->where('table.key', 'admin.rate-limits')
                ->where('table.pagination.total', 1)
                ->where('table.state.filters.family', 'all')
                ->where('table.state.filters.activity', 'all')
                ->where('table.exports.endpoint', route('admin.exports.data-table'))
                ->where('policies.0.policy', 'auth.login')
                ->where('policies.0.policyFamily', 'auth')
                ->where('policies.0.maxAttempts', 5)
                ->where('policies.0.rejections', 2)
                ->where('policies.0.distinctKeys', 1)
                ->where('filterOptions.families.0', 'admin')
                ->where('policyOptions.0.value', 'auth.login')
            );
    }

    public function test_admin_reset_clears_one_concrete_rate_limit_counter_and_records_security_audit(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $limiterKey = 'auth.login|user:blocked@example.test|ip:127.0.0.1';
        $requestId = 'rate-limit-reset-request';

        RateLimiter::hit($limiterKey, 60);
        $this->app->make(RateLimitRejectionRecorder::class)->record('auth.login', $limiterKey, 'req-rate-limit-1');

        self::assertTrue(RateLimiter::tooManyAttempts($limiterKey, 1));

        $this->actingAs($admin)
            ->withHeader('X-Request-Id', $requestId)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->post('/admin/rate-limits/reset', [
                'policy' => 'auth.login',
                'limiter_key' => $limiterKey,
                'reason' => 'Verified support unlock request.',
            ])
            ->assertRedirect('/admin/rate-limits');

        self::assertFalse(RateLimiter::tooManyAttempts($limiterKey, 1));
        self::assertDatabaseMissing(DatabaseTable::RATE_LIMIT_REJECTIONS, [
            'policy' => 'auth.login',
            'limiter_key_hash' => hash('sha256', $limiterKey),
        ]);
        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'identity',
            'action' => 'rate_limit.counter_reset',
            'result' => 'succeeded',
            'source' => 'admin',
            'actor_public_id' => $admin->public_id,
            'target_type' => 'rate_limit_counter',
            'correlation_id' => $requestId,
            'reason' => 'Verified support unlock request.',
            'is_security' => true,
        ]);

        $metadata = DB::table(DatabaseTable::AUDIT_EVENTS)
            ->where('action', 'rate_limit.counter_reset')
            ->value('metadata');

        self::assertIsString($metadata);
        self::assertSame([
            'policy' => 'auth.login',
            'limiter_key' => $limiterKey,
            'limiter_key_hash' => hash('sha256', $limiterKey),
        ], json_decode($metadata, true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_admin_rate_limit_thresholds_are_not_editable_routes(): void
    {
        self::assertTrue(Route::has('admin.rate-limits.index'));
        self::assertTrue(Route::has('admin.rate-limits.reset'));
        self::assertFalse(Route::has('admin.rate-limits.update'));
        self::assertFalse(Route::has('admin.rate-limits.destroy'));
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

    private static function stringListContains(mixed $values, string $value): bool
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        if ($values instanceof \Traversable) {
            $values = iterator_to_array($values);
        }

        if (! is_array($values)) {
            return false;
        }

        foreach ($values as $key => $item) {
            if ($key === $value || $item === $value) {
                return true;
            }
        }

        return false;
    }
}
