<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentBootstrapSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
use Database\Seeders\SystemBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

final class DemoResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_reset_refuses_production_environment(): void
    {
        Config::set('app.env', 'production');

        $exitCode = Artisan::call('demo:reset');

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'Refusing to reset Atlas demo data in the [production] environment.',
            Artisan::output(),
        );
    }

    public function test_production_safe_database_seeder_does_not_create_demo_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas(DatabaseTable::ROLES, [
            'name' => StarterRoleName::Administrator->value,
        ]);
        $this->assertGreaterThan(0, DB::table(DatabaseTable::PERMISSIONS)->count());
        $this->assertDatabaseMissing(DatabaseTable::USERS, [
            'email' => DevelopmentBootstrapSeeder::PREVIEW_EMAIL,
        ]);
        $this->assertDatabaseHas(DatabaseTable::TEAMS, ['name' => SystemBootstrapSeeder::ADMINISTRATION_TEAM_NAME]);
    }

    public function test_development_bootstrap_seeder_creates_only_clean_admin_foundation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DevelopmentBootstrapSeeder::class);

        $user = User::query()
            ->where('email', DevelopmentBootstrapSeeder::PREVIEW_EMAIL)
            ->firstOrFail();

        $this->assertSame('Admin', $user->name);
        $this->assertSame('sensitive', $user->account_sensitivity);
        $this->assertTrue(Hash::check(DevelopmentBootstrapSeeder::PREVIEW_PASSWORD, $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseCount(DatabaseTable::USERS, 1);
        $this->assertDatabaseHas(DatabaseTable::TEAMS, ['name' => SystemBootstrapSeeder::ADMINISTRATION_TEAM_NAME]);
        $this->assertDatabaseCount(DatabaseTable::TEAMS, 1);
        $this->assertDatabaseHas(DatabaseTable::ROLES, ['name' => StarterRoleName::Administrator->value]);
        $this->assertDatabaseCount(DatabaseTable::USER_ONBOARDING_PACKAGES, 0);
        $this->assertDatabaseCount(DatabaseTable::FILE_OBJECTS, 0);
        $this->assertDatabaseCount(DatabaseTable::NOTIFICATIONS, 0);
        $this->assertDatabaseCount(DatabaseTable::MANAGED_PROCESS_RUNS, 0);
        $this->assertDatabaseCount(DatabaseTable::MANAGED_PROCESS_SCHEDULES, 0);
        $this->assertDatabaseCount(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS, 0);

        $teamPublicId = DB::table(DatabaseTable::TEAMS)->where('name', SystemBootstrapSeeder::ADMINISTRATION_TEAM_NAME)->value('public_id');

        self::assertIsString($teamPublicId);

        $this->actingAs($user)
            ->withSession([
                'active_team_public_id' => $teamPublicId,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/SystemStatus'));
    }

    public function test_development_demo_seeder_creates_idempotent_time_tracking_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DevelopmentBootstrapSeeder::class);

        $this->seed(DevelopmentDemoSeeder::class);
        $this->seed(DevelopmentDemoSeeder::class);

        $this->assertDatabaseCount(DatabaseTable::USERS, 57);
        $this->assertDatabaseCount(DatabaseTable::TEAMS, 3);
        $this->assertDatabaseHas(DatabaseTable::USERS, [
            'email' => 'tt.one.minute.policy.north@example.test',
            'name' => 'TT One Minute Policy Test User - North',
        ]);
        $this->assertDatabaseHas(DatabaseTable::TEAMS, ['name' => 'TT Demo Team North']);
        $this->assertDatabaseHas(DatabaseTable::TEAMS, ['name' => 'TT Demo Team South']);
        $this->assertDatabaseCount(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS, 54);
        $this->assertDatabaseCount(DatabaseTable::TIME_TRACKING_MAINTENANCE_WINDOWS, 1);
        $this->assertGreaterThan(0, DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->count());
        $this->assertGreaterThan(0, DB::table(DatabaseTable::TIME_TRACKING_BREAKS)->count());
        $this->assertGreaterThan(0, DB::table(DatabaseTable::TIME_TRACKING_MAINTENANCE_AFFECTED_SESSIONS)->count());
        $this->assertGreaterThan(0, DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK)->count());
        $this->assertGreaterThan(0, DB::table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)->count());
        $this->assertGreaterThan(0, DB::table(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS)->count());
        $this->assertSame(
            DB::table(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS)->count(),
            DB::table(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS)
                ->where('module_key', 'system')
                ->where('context_key', 'System')
                ->count(),
            'Development TimeTracking demo segments must use the current system context contract.',
        );
        $this->assertSame(
            DB::table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS)->count(),
            DB::table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS.' as correction_requests')
                ->join(DatabaseTable::TIME_TRACKING_CORRECTION_PROPOSALS.' as correction_proposals', 'correction_proposals.correction_request_id', '=', 'correction_requests.id')
                ->whereNotNull('correction_proposals.proposed_started_at')
                ->whereNotNull('correction_proposals.proposed_ended_at')
                ->count(),
            'Every demo correction request must expose proposed start and end timestamps.',
        );
        foreach (['TT Demo Team North', 'TT Demo Team South'] as $teamName) {
            foreach (['work_session', 'break', 'other_work'] as $sourceType) {
                $this->assertGreaterThan(
                    0,
                    DB::table(DatabaseTable::TIME_TRACKING_CORRECTION_REQUESTS.' as correction_requests')
                        ->join(DatabaseTable::TEAMS.' as teams', 'correction_requests.team_id', '=', 'teams.id')
                        ->where('teams.name', $teamName)
                        ->where('correction_requests.source_type', $sourceType)
                        ->count(),
                    sprintf('Expected %s demo correction source in %s.', $sourceType, $teamName),
                );
            }
        }
        $this->assertGreaterThan(0, DB::table(DatabaseTable::TIME_TRACKING_BREAKS)
            ->where('closure_reason', 'normal')
            ->where('requires_manager_review', false)
            ->where('exact_seconds', '>', 900)
            ->count());

        $specialPolicy = DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->where('users.email', 'tt.one.minute.policy.north@example.test')
            ->first([
                'team_user_assignments.id',
                'team_user_assignments.inactivity_timeout_minutes',
                'team_user_assignments.session_max_lifetime_minutes',
            ]);

        self::assertNotNull($specialPolicy);
        self::assertSame(1, $this->intValue(data_get($specialPolicy, 'inactivity_timeout_minutes')));
        self::assertNull(data_get($specialPolicy, 'session_max_lifetime_minutes'));
        $this->assertDatabaseHas(DatabaseTable::TIME_TRACKING_BREAK_POLICIES, [
            'scope_type' => 'user_team',
            'scope_id' => $this->intValue(data_get($specialPolicy, 'id')),
            'daily_limit_seconds' => 60,
            'maximum_single_break_seconds' => 60,
        ]);

        $this->assertDatabaseCount(DatabaseTable::USER_ONBOARDING_PACKAGES, 0);
        $this->assertDatabaseCount(DatabaseTable::FAILED_JOBS, 0);
        $this->assertDatabaseCount(DatabaseTable::RATE_LIMIT_REJECTIONS, 0);
        $this->assertDatabaseCount(DatabaseTable::MANAGED_PROCESS_RUNS, 0);
        $this->assertDatabaseCount(DatabaseTable::IMPORT_EXECUTIONS, 0);
        $this->assertDatabaseCount(DatabaseTable::IMPORT_ROW_ERRORS, 0);
        $this->assertDatabaseCount(DatabaseTable::INTEGRATION_CONNECTIONS, 0);
        $this->assertDatabaseCount(DatabaseTable::INTEGRATION_SYNC_RUNS, 0);
        $this->assertDatabaseCount(DatabaseTable::INTEGRATION_CIRCUIT_BREAKERS, 0);
        $this->assertDatabaseCount(DatabaseTable::FEATURE_FLAG_GLOBAL_VALUES, 0);
        $this->assertDatabaseCount(DatabaseTable::FEATURE_FLAG_TEAM_VALUES, 0);
        $this->assertDatabaseCount(DatabaseTable::FEATURE_FLAG_HISTORY, 0);
        $this->assertDatabaseCount(DatabaseTable::NOTIFICATIONS, 0);
        $this->assertDatabaseCount(DatabaseTable::AUDIT_EVENTS, 0);
        $this->assertDatabaseCount(DatabaseTable::AUDIT_SECURITY_EVENTS, 0);
        $this->assertDatabaseCount(DatabaseTable::FILE_OBJECTS, 0);
        $this->assertDatabaseCount(DatabaseTable::FILE_SCAN_EVIDENCE, 0);
        $this->assertDatabaseMissing(DatabaseTable::MODULE_GLOBAL_STATES, [
            'module_key' => 'demo',
        ]);
        $this->assertDatabaseMissing(DatabaseTable::MODULE_TEAM_STATES, [
            'module_key' => 'demo',
        ]);
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
