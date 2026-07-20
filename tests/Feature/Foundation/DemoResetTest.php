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

        $this->assertDatabaseMissing(DatabaseTable::USERS, [
            'email' => DevelopmentDemoSeeder::PREVIEW_EMAIL,
        ]);
    }

    public function test_development_demo_seeder_creates_the_preview_account(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);

        $user = User::query()
            ->where('email', DevelopmentDemoSeeder::PREVIEW_EMAIL)
            ->firstOrFail();

        $this->assertSame('Admin', $user->name);
        $this->assertTrue(Hash::check(DevelopmentDemoSeeder::PREVIEW_PASSWORD, $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas(DatabaseTable::TEAMS, ['name' => 'Collections North']);
        $this->assertDatabaseHas(DatabaseTable::TEAMS, ['name' => 'Collections South']);
        $this->assertDatabaseHas(DatabaseTable::TEAMS, ['name' => 'Back Office']);
        $this->assertDatabaseHas(DatabaseTable::ROLES, ['name' => StarterRoleName::Administrator->value]);
        $this->assertDatabaseHas(DatabaseTable::MODULE_GLOBAL_STATES, [
            'module_key' => 'integrations',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas(DatabaseTable::USERS, ['email' => 'demo.user.01@example.test']);
        $this->assertDatabaseHas(DatabaseTable::USERS, ['email' => 'demo.copy.north@example.test']);
        $this->assertDatabaseHas(DatabaseTable::USERS, ['email' => 'demo.copy.south@example.test']);
        $this->assertDatabaseHas(DatabaseTable::USERS, ['email' => 'demo.copy.backoffice@example.test']);
        $this->assertDatabaseHas(DatabaseTable::USERS, ['email' => 'demo.multi.team@example.test']);
        $this->assertDatabaseHas(DatabaseTable::USER_ONBOARDING_PACKAGES, ['package_name' => 'north.collections.agent']);
        $this->assertDatabaseHas(DatabaseTable::USER_ONBOARDING_PACKAGES, ['package_name' => 'north.collections.team_leader']);
        $this->assertDatabaseHas(DatabaseTable::USER_ONBOARDING_PACKAGES, ['package_name' => 'south.collections.skip_tracer']);
        $this->assertDatabaseHas(DatabaseTable::USER_ONBOARDING_PACKAGES, ['package_name' => 'back_office.specialist']);
        $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
            'original_name' => 'demo-clean-payment-confirmation.txt',
            'scan_state' => 'clean',
        ]);
        $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
            'original_name' => 'demo-duplicate-payment-confirmation.txt',
            'scan_state' => 'clean',
            'physical_owner' => false,
        ]);
        $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
            'original_name' => 'demo-pending-large-import-attachment.txt',
            'scan_state' => 'pending',
        ]);
        $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
            'original_name' => 'demo-infected-suspicious-attachment.txt',
            'scan_state' => 'infected',
        ]);
        $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
            'original_name' => 'demo-failed-scanner-timeout.txt',
            'scan_state' => 'failed',
        ]);
        $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
            'original_name' => 'demo-unsupported-archive.txt',
            'scan_state' => 'unsupported',
        ]);

        $multiTeamUser = User::query()
            ->where('email', 'demo.multi.team@example.test')
            ->firstOrFail();

        $this->assertSame(2, DB::table(DatabaseTable::USER_ONBOARDING_PACKAGES)
            ->where('user_id', $multiTeamUser->id)
            ->count());

        $teamPublicId = DB::table(DatabaseTable::TEAMS)->where('name', 'Collections North')->value('public_id');

        self::assertIsString($teamPublicId);

        $this->actingAs($user)
            ->withSession([
                'active_team_public_id' => $teamPublicId,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/integrations')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Integrations/Index')
                ->where('integrations', [])
                ->where('recentRuns', []));
    }
}
