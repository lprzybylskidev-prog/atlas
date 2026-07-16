<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
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

        $this->assertDatabaseMissing('users', [
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
        $this->assertDatabaseHas('teams', ['name' => 'Collections North']);
        $this->assertDatabaseHas('teams', ['name' => 'Collections South']);
        $this->assertDatabaseHas('teams', ['name' => 'Back Office']);
        $this->assertDatabaseHas('roles', ['name' => StarterRoleName::Administrator->value]);
        $this->assertDatabaseHas('users', ['email' => 'demo.user.01@example.test']);
        $this->assertDatabaseHas('user_onboarding_packages', ['package_name' => 'collections.agent']);
        $this->assertDatabaseHas('user_onboarding_packages', ['package_name' => 'collections.team_leader']);
        $this->assertDatabaseHas('user_onboarding_packages', ['package_name' => 'back_office.specialist']);
    }
}
