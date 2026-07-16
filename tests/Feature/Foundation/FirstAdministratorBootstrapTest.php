<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Users\Infrastructure\Notifications\FirstPasswordSetupNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

final class FirstAdministratorBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_administrator_bootstrap_creates_admin_and_sends_first_password_link(): void
    {
        Notification::fake();

        $exitCode = Artisan::call('atlas:first-administrator', [
            '--name' => 'First Admin',
            '--email' => 'first.admin@example.test',
            '--team' => 'Operations',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $user = User::query()->where('email', 'first.admin@example.test')->firstOrFail();
        $teamId = DB::table('teams')->where('name', 'Operations')->value('id');
        $role = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();

        self::assertFalse($user->hasSetFirstPassword());
        self::assertNotNull($teamId);
        self::assertDatabaseHas('team_user_assignments', [
            'team_id' => $teamId,
            'user_id' => $user->id,
        ]);
        self::assertDatabaseHas('model_has_roles', [
            'role_id' => $role->id,
            'model_id' => $user->id,
            'team_id' => $teamId,
        ]);
        self::assertDatabaseHas('password_reset_tokens', [
            'email' => 'first.admin@example.test',
        ]);
        self::assertDatabaseHas('audit_events', [
            'module' => 'authorization',
            'action' => 'authorization.first_administrator_bootstrap',
            'target_public_id' => $user->public_id,
        ]);

        Notification::assertSentOnDemand(FirstPasswordSetupNotification::class);
    }

    public function test_first_administrator_bootstrap_is_unavailable_after_administrator_exists(): void
    {
        Notification::fake();

        Artisan::call('atlas:first-administrator', [
            '--name' => 'First Admin',
            '--email' => 'first.admin@example.test',
        ]);

        $exitCode = Artisan::call('atlas:first-administrator', [
            '--name' => 'Second Admin',
            '--email' => 'second.admin@example.test',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertDatabaseMissing('users', [
            'email' => 'second.admin@example.test',
        ]);
    }
}
