<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Observability\ApplicationLogReader;
use App\Shared\Infrastructure\Observability\SensitiveDataRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminApplicationLogBrowserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_curated_application_logs_without_supplying_a_filesystem_path(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $path = tempnam(sys_get_temp_dir(), 'atlas-log-');
        self::assertIsString($path);

        try {
            file_put_contents($path, json_encode([
                'message' => 'Identity operation for user@example.test',
                'context' => [
                    'correlation_id' => 'request-log-1',
                    'module' => 'identity',
                    'source' => 'http',
                    'event_name' => 'identity.probe',
                ],
                'level_name' => 'INFO',
                'channel' => 'testing',
                'datetime' => '2026-07-17T12:00:00+00:00',
                'extra' => ['request_id' => 'request-log-1'],
            ], JSON_THROW_ON_ERROR).PHP_EOL);

            $this->app->instance(ApplicationLogReader::class, new ApplicationLogReader(new SensitiveDataRedactor, $path));

            $this->actingAs($admin)
                ->withSession([
                    'active_team_public_id' => $team->public_id,
                    'auth.password_confirmed_at' => now()->unix(),
                ])
                ->get('/admin/logs?path=/etc/passwd&search=identity')
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Admin/Logs/Index')
                    ->where('auth.availableAdminRoutes', function (Collection $routes): bool {
                        return $routes->contains('admin.logs.index');
                    })
                    ->where('summary.pathLabel', basename($path))
                    ->where('summary.rows', 1)
                    ->where('logs.0.module', 'identity')
                    ->where('logs.0.message', 'Identity operation for [redacted]')
                    ->where('logs.0.correlationId', 'request-log-1')
                );
        } finally {
            @unlink($path);
        }
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
