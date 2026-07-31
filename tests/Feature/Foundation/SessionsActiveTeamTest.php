<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class SessionsActiveTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection($this->redisSessionConnection())->flushdb();
    }

    protected function tearDown(): void
    {
        Redis::connection($this->redisSessionConnection())->flushdb();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_single_assigned_team_is_selected_and_session_metadata_is_recorded(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-16 10:00:00'));

        $user = User::factory()->create(['name' => 'Session User']);
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($user, $team, StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 Firefox/140.0')
            ->get('/')
            ->assertOk()
            ->assertSessionHas('active_team_public_id', $team->public_id);

        $sessions = $this->app->make(UserSessionRegistry::class)->activeForUser((string) $user->public_id);

        self::assertCount(1, $sessions);
        self::assertSame('Session User', $sessions[0]->userName);
        self::assertSame('Firefox', $sessions[0]->browser);
        self::assertSame((string) $team->public_id, $sessions[0]->activeTeamPublicId);
        self::assertSame('2026-07-16T10:00:00+02:00', $sessions[0]->createdAt);
        self::assertSame('2026-07-16T10:00:00+02:00', $sessions[0]->lastActivityAt);
    }

    public function test_multiple_teams_require_explicit_selection_and_switching_is_audited(): void
    {
        $user = User::factory()->create();
        $first = Team::query()->create(['name' => 'Alpha']);
        $second = Team::query()->create(['name' => 'Beta']);
        $this->assignStarterRoleInTeam($user, $first, StarterRoleName::WorkspaceAccess->value);
        $this->assignStarterRoleInTeam($user, $second, StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('team.select'));

        $this->actingAs($user)
            ->get('/team/select')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Teams/Select')
                ->has('teams', 2));

        $this->actingAs($user)
            ->post('/team/select', ['team_public_id' => $second->public_id])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'identity',
            'action' => 'session.active_team_switched',
            'actor_public_id' => $user->public_id,
            'target_public_id' => $second->public_id,
        ]);
    }

    public function test_team_switch_is_session_scoped_and_does_not_require_current_route_permission(): void
    {
        $user = User::factory()->create();
        $first = Team::query()->create(['name' => 'Alpha']);
        $second = Team::query()->create(['name' => 'Beta']);
        $this->assignStarterRoleInTeam($user, $first, StarterRoleName::WorkspaceAccess->value);
        $this->assignStarterRoleInTeam($user, $second, StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($user)
            ->from('/admin')
            ->post('/team/switch', ['team_public_id' => $second->public_id])
            ->assertRedirect('/admin')
            ->assertSessionHas('active_team_public_id', $second->public_id);
    }

    public function test_inactivity_and_maximum_lifetime_are_enforced_without_mutating_session_config(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-16 10:00:00'));

        $user = User::factory()->create([
            'inactivity_timeout_minutes' => 5,
            'session_max_lifetime_minutes' => 60,
        ]);
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($user, $team, StarterRoleName::WorkspaceAccess->value);
        $configuredLifetime = config('session.lifetime');

        Carbon::setTestNow(Carbon::parse('2026-07-16 10:06:00'));

        $this->actingAs($user)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'atlas_session_created_at' => '2026-07-16T10:00:00+02:00',
                'atlas_session_last_activity_at' => '2026-07-16T10:00:00+02:00',
            ])
            ->get('/')
            ->assertRedirect(route('login'));

        self::assertSame($configuredLifetime, config('session.lifetime'));
    }

    public function test_admin_users_table_exposes_default_visible_online_status(): void
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::USERS);
        $onlineColumn = array_values(array_filter($definition->columns, static fn ($column): bool => $column->key === 'online'))[0] ?? null;

        self::assertNotNull($onlineColumn);
        self::assertTrue($onlineColumn->defaultVisible);

        $admin = User::factory()->create(['name' => 'Admin Actor']);
        $target = User::factory()->create(['name' => 'Online Target']);
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($admin, $team, StarterRoleName::Administrator->value);
        $this->assignStarterRoleInTeam($target, $team, StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($target)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/')
            ->assertOk();

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->where('users.1.name', 'Online Target')
                ->where('users.1.online', true));
    }

    public function test_security_actions_invalidate_user_sessions(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Actor']);
        $target = User::factory()->create(['name' => 'Target User', 'email_verified_at' => now(), 'first_password_set_at' => now()]);
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($admin, $team, StarterRoleName::Administrator->value);
        $this->assignStarterRoleInTeam($target, $team, StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($target)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/')
            ->assertOk();

        self::assertCount(1, $this->app->make(UserSessionRegistry::class)->activeForUser((string) $target->public_id));

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->post('/admin/users/'.$target->public_id.'/require-email-verification')
            ->assertRedirect(route('admin.users.index'));

        self::assertCount(0, $this->app->make(UserSessionRegistry::class)->activeForUser((string) $target->public_id));
    }

    public function test_second_device_login_requires_explicit_confirmation_before_terminating_existing_session(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $user = User::factory()->create([
            'email' => 'session-conflict@example.test',
            'password' => Hash::make('CorrectPass12!'),
        ]);
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($user, $team, StarterRoleName::WorkspaceAccess->value);
        $this->recordSessionMetadata($user, $team, 'existing-working-session');

        $this->from('/login')->post('/login', [
            'email' => 'session-conflict@example.test',
            'password' => 'CorrectPass12!',
        ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('session_conflict');

        $this->assertGuest();
        self::assertCount(1, $this->app->make(UserSessionRegistry::class)->activeForUser((string) $user->public_id));
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'identity',
            'action' => 'auth.session_conflict',
            'result' => 'rejected',
            'target_public_id' => $user->public_id,
        ]);

        $this->post('/login', [
            'email' => 'session-conflict@example.test',
            'password' => 'CorrectPass12!',
            'terminate_existing_session' => true,
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        self::assertCount(0, $this->app->make(UserSessionRegistry::class)->activeForUser((string) $user->public_id));
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'identity',
            'action' => 'auth.session_conflict_resolved',
            'result' => 'succeeded',
            'target_public_id' => $user->public_id,
        ]);
    }

    public function test_logout_removes_session_metadata_from_registry(): void
    {
        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($user, $team, StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/')
            ->assertOk();

        self::assertCount(1, $this->app->make(UserSessionRegistry::class)->activeForUser((string) $user->public_id));

        $this->post('/logout')->assertRedirect('/');

        self::assertCount(0, $this->app->make(UserSessionRegistry::class)->activeForUser((string) $user->public_id));
    }

    private function assignStarterRoleInTeam(User $user, Team $team, string $roleName): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $role = Role::query()->where('name', $roleName)->firstOrFail();

        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insertOrIgnore([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(DatabaseTable::MODEL_HAS_ROLES)->insertOrIgnore([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    private function recordSessionMetadata(User $user, Team $team, string $sessionId): void
    {
        $session = $this->app['session.store'];
        $session->setId($sessionId);
        $session->start();
        $session->put('active_team_public_id', (string) $team->public_id);

        $request = Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/140.0']);
        $request->setLaravelSession($session);
        $request->setUserResolver(static fn (): User => $user);

        $this->app->make(UserSessionRegistry::class)->touch($request);
        $session->save();
        $session->setId('login-attempt-session');
        $session->start();
        $session->flush();
    }

    private function redisSessionConnection(): ?string
    {
        $connection = config('session.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }
}
