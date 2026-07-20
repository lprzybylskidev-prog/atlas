<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
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
            'email' => DevelopmentDemoSeeder::PREVIEW_EMAIL,
        ]);
    }

    public function test_development_demo_seeder_creates_only_clean_admin_foundation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DevelopmentDemoSeeder::class);

        $user = User::query()
            ->where('email', DevelopmentDemoSeeder::PREVIEW_EMAIL)
            ->firstOrFail();

        $this->assertSame('Admin', $user->name);
        $this->assertTrue(Hash::check(DevelopmentDemoSeeder::PREVIEW_PASSWORD, $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseCount(DatabaseTable::USERS, 1);
        $this->assertDatabaseHas(DatabaseTable::TEAMS, ['name' => DevelopmentDemoSeeder::ADMIN_TEAM_NAME]);
        $this->assertDatabaseCount(DatabaseTable::TEAMS, 1);
        $this->assertDatabaseHas(DatabaseTable::ROLES, ['name' => StarterRoleName::Administrator->value]);
        $this->assertDatabaseCount(DatabaseTable::USER_ONBOARDING_PACKAGES, 0);
        $this->assertDatabaseCount(DatabaseTable::FILE_OBJECTS, 0);
        $this->assertDatabaseCount(DatabaseTable::NOTIFICATIONS, 0);
        $this->assertDatabaseCount(DatabaseTable::MANAGED_PROCESS_RUNS, 0);
        $this->assertDatabaseCount(DatabaseTable::MANAGED_PROCESS_SCHEDULES, 0);
        $this->assertDatabaseCount(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS, 0);

        $teamPublicId = DB::table(DatabaseTable::TEAMS)->where('name', DevelopmentDemoSeeder::ADMIN_TEAM_NAME)->value('public_id');

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
}
