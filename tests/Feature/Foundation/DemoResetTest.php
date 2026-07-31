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

    public function test_development_demo_seeder_is_noop_after_phase_25_cleanup(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DevelopmentBootstrapSeeder::class);

        $this->seed(DevelopmentDemoSeeder::class);
        $this->seed(DevelopmentDemoSeeder::class);

        $this->assertDatabaseCount(DatabaseTable::USERS, 1);
        $this->assertDatabaseCount(DatabaseTable::TEAMS, 1);
        $this->assertDatabaseCount(DatabaseTable::USER_ONBOARDING_PACKAGES, 0);
        $this->assertDatabaseCount(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS, 0);
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
}
