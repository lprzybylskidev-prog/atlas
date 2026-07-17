<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Application\Admin\HighRiskAdministrativeOperation;
use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Modules\Core\Identity\Application\Admin\ImpersonationSimulationStore;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminModeImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_mode_inactivity_expiry_returns_to_normal_mode(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-17 12:31:00'));

        [$admin, $team] = $this->adminActor();

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                AdministrativeSessionManager::ENTERED_AT => '2026-07-17T12:00:00+02:00',
                AdministrativeSessionManager::LAST_ACTIVITY_AT => '2026-07-17T12:00:00+02:00',
            ])
            ->get('/admin')
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas(AdministrativeSessionManager::PENDING_REAUTHENTICATION, AdministrativeSessionManager::PENDING_ENTER)
            ->assertSessionMissing(AdministrativeSessionManager::ENTERED_AT);
    }

    public function test_admin_mode_absolute_lifetime_expiry_returns_to_normal_mode(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-17 16:01:00'));

        [$admin, $team] = $this->adminActor();

        $this->actingAs($admin)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                AdministrativeSessionManager::ENTERED_AT => '2026-07-17T12:00:00+02:00',
                AdministrativeSessionManager::LAST_ACTIVITY_AT => '2026-07-17T15:59:00+02:00',
            ])
            ->get('/admin')
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas(AdministrativeSessionManager::PENDING_REAUTHENTICATION, AdministrativeSessionManager::PENDING_ENTER)
            ->assertSessionMissing(AdministrativeSessionManager::ENTERED_AT);
    }

    public function test_admin_mode_is_entered_through_confirm_password_flow(): void
    {
        [$admin, $team] = $this->adminActor();

        $this->actingAs($admin)
            ->withSession(['active_team_public_id' => (string) $team->public_id])
            ->get('/admin')
            ->assertRedirect(route('password.confirm'));

        $this->post('/user/confirm-password', [
            'password' => 'password',
        ])
            ->assertRedirect(route('admin.system-status'))
            ->assertSessionHas(AdministrativeSessionManager::ENTERED_AT)
            ->assertSessionHas(AdministrativeSessionManager::LAST_ACTIVITY_AT)
            ->assertSessionMissing(AdministrativeSessionManager::PENDING_REAUTHENTICATION);
    }

    public function test_high_risk_authorization_expires_independently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-17 12:06:00'));

        [$admin, $team] = $this->adminActor();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->withSession([
                ...$this->adminSession($team),
                AdministrativeSessionManager::HIGH_RISK_CONFIRMED_AT => '2026-07-17T12:00:00+02:00',
            ])
            ->post('/admin/users/'.$target->public_id.'/reset-mfa')
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas(AdministrativeSessionManager::PENDING_REAUTHENTICATION, AdministrativeSessionManager::PENDING_HIGH_RISK);
    }

    public function test_known_administrative_operation_classes_are_high_risk(): void
    {
        self::assertSame([
            'hard_delete',
            'irreversible_anonymization',
            'mfa_reset',
            'administrator_permission_change',
            'impersonation_sensitive_override',
            'closed_period_time_tracking_correction',
        ], array_map(
            static fn (HighRiskAdministrativeOperation $operation): string => $operation->value,
            HighRiskAdministrativeOperation::cases(),
        ));
    }

    public function test_impersonation_rejects_administrator_and_technical_accounts(): void
    {
        [$admin, $team] = $this->adminActor();
        $otherAdmin = User::factory()->create();
        $technical = User::factory()->create(['account_sensitivity' => 'technical']);
        $this->assignStarterRoleInTeam($otherAdmin, $team, StarterRoleName::Administrator->value);
        $this->assignStarterRoleInTeam($technical, $team, StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/users/'.$otherAdmin->public_id.'/impersonate', [
                'team_public_id' => $team->public_id,
                'reason' => 'Support investigation',
            ])
            ->assertSessionHasErrors('user');

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/users/'.$technical->public_id.'/impersonate', [
                'team_public_id' => $team->public_id,
                'reason' => 'Support investigation',
            ])
            ->assertSessionHasErrors('user');
    }

    public function test_sensitive_impersonation_requires_override_permission_and_fresh_high_risk(): void
    {
        [$admin, $team] = $this->adminActor();
        $sensitive = User::factory()->create(['account_sensitivity' => 'sensitive']);
        $this->assignStarterRoleInTeam($sensitive, $team, StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/users/'.$sensitive->public_id.'/impersonate', [
                'team_public_id' => $team->public_id,
                'reason' => 'Sensitive support investigation',
                'override_sensitive' => true,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(ImpersonationManager::USER_PUBLIC_ID, (string) $sensitive->public_id);
    }

    public function test_impersonation_uses_target_permissions_without_hidden_admin_bypass(): void
    {
        [$admin, $team] = $this->adminActor();
        $target = User::factory()->create();

        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $target->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession([
                ...$this->adminSession($team),
                ImpersonationManager::SESSION_ID => '01K0DXCKWJ3N8B6N7VHQ0A0001',
                ImpersonationManager::ACTOR_PUBLIC_ID => (string) $admin->public_id,
                ImpersonationManager::ACTOR_TEAM_PUBLIC_ID => (string) $team->public_id,
                ImpersonationManager::USER_PUBLIC_ID => (string) $target->public_id,
                ImpersonationManager::USER_NAME => (string) $target->name,
                ImpersonationManager::TEAM_PUBLIC_ID => (string) $team->public_id,
                ImpersonationManager::TEAM_NAME => (string) $team->name,
                ImpersonationManager::REASON => 'Permission verification',
                ImpersonationManager::STARTED_AT => now()->toIso8601String(),
            ])
            ->get('/')
            ->assertForbidden();
    }

    public function test_impersonation_ends_when_target_is_deactivated(): void
    {
        [$admin, $team] = $this->adminActor();
        $target = User::factory()->create();
        $this->assignStarterRoleInTeam($target, $team, StarterRoleName::WorkspaceAccess->value);
        $target->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

        $this->actingAs($admin)
            ->withSession([
                ...$this->adminSession($team),
                ImpersonationManager::SESSION_ID => '01K0DXCKWJ3N8B6N7VHQ0A0003',
                ImpersonationManager::ACTOR_PUBLIC_ID => (string) $admin->public_id,
                ImpersonationManager::ACTOR_TEAM_PUBLIC_ID => (string) $team->public_id,
                ImpersonationManager::USER_PUBLIC_ID => (string) $target->public_id,
                ImpersonationManager::USER_NAME => (string) $target->name,
                ImpersonationManager::TEAM_PUBLIC_ID => (string) $team->public_id,
                ImpersonationManager::TEAM_NAME => (string) $team->name,
                ImpersonationManager::REASON => 'Invalidation check',
                ImpersonationManager::STARTED_AT => now()->toIso8601String(),
            ])
            ->get('/')
            ->assertOk()
            ->assertSessionMissing(ImpersonationManager::SESSION_ID);
    }

    public function test_impersonation_simulation_state_is_deleted_when_impersonation_ends(): void
    {
        [$admin, $team] = $this->adminActor();
        $target = User::factory()->create();
        $sessionId = '01K0DXCKWJ3N8B6N7VHQ0A0002';
        $store = $this->app->make(ImpersonationSimulationStore::class);

        $store->put($sessionId, 'time-tracking.current-state', ['running' => true]);
        self::assertSame(['running' => true], $store->get($sessionId, 'time-tracking.current-state'));

        $this->actingAs($admin)
            ->withSession([
                ...$this->adminSession($team),
                ImpersonationManager::SESSION_ID => $sessionId,
                ImpersonationManager::ACTOR_PUBLIC_ID => (string) $admin->public_id,
                ImpersonationManager::ACTOR_TEAM_PUBLIC_ID => (string) $team->public_id,
                ImpersonationManager::USER_PUBLIC_ID => (string) $target->public_id,
                ImpersonationManager::TEAM_PUBLIC_ID => (string) $team->public_id,
                ImpersonationManager::REASON => 'Simulation cleanup',
            ])
            ->delete('/impersonation')
            ->assertRedirect(route('admin.system-status'));

        self::assertNull($store->get($sessionId, 'time-tracking.current-state'));
    }

    public function test_external_effect_operations_require_acknowledgement_during_impersonation(): void
    {
        Route::post('/testing/external-effect', static fn () => response()->noContent())
            ->middleware(['web', 'auth', 'impersonation.external-effect']);

        [$admin, $team] = $this->adminActor();
        $target = User::factory()->create();
        $this->assignStarterRoleInTeam($target, $team, StarterRoleName::WorkspaceAccess->value);

        $session = [
            ...$this->adminSession($team),
            ImpersonationManager::SESSION_ID => '01K0DXCKWJ3N8B6N7VHQ0A0004',
            ImpersonationManager::ACTOR_PUBLIC_ID => (string) $admin->public_id,
            ImpersonationManager::ACTOR_TEAM_PUBLIC_ID => (string) $team->public_id,
            ImpersonationManager::USER_PUBLIC_ID => (string) $target->public_id,
            ImpersonationManager::USER_NAME => (string) $target->name,
            ImpersonationManager::TEAM_PUBLIC_ID => (string) $team->public_id,
            ImpersonationManager::TEAM_NAME => (string) $team->name,
            ImpersonationManager::REASON => 'External effect warning',
            ImpersonationManager::STARTED_AT => now()->toIso8601String(),
        ];

        $this->actingAs($admin)
            ->withSession($session)
            ->from('/testing/source')
            ->post('/testing/external-effect')
            ->assertRedirect('/testing/source')
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->withSession($session)
            ->post('/testing/external-effect', [
                'impersonation_external_effect_acknowledged' => true,
            ])
            ->assertNoContent();
    }

    public function test_admin_can_view_impersonation_audit_session_detail(): void
    {
        [$admin, $team] = $this->adminActor();
        $target = User::factory()->create();
        $this->assignStarterRoleInTeam($target, $team, StarterRoleName::WorkspaceAccess->value);

        $response = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/users/'.$target->public_id.'/impersonate', [
                'team_public_id' => $team->public_id,
                'reason' => 'Audit detail check',
            ]);

        $sessionId = $this->app->make(Session::class)->get(ImpersonationManager::SESSION_ID);
        self::assertIsString($sessionId);

        $this->delete('/impersonation')->assertRedirect(route('admin.system-status'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/audit/impersonation/'.$sessionId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Audit/ImpersonationSession')
                ->where('session.id', $sessionId)
                ->where('session.reason', 'Audit detail check'));
    }

    public function test_admin_can_view_security_history_for_all_users_without_realtime_notification(): void
    {
        [$admin, $team] = $this->adminActor();
        $target = User::factory()->create(['name' => 'Security Target', 'email' => 'security-target@example.test']);
        $otherTarget = User::factory()->create(['name' => 'Other Security User', 'email' => 'other-security@example.test']);
        $this->assignStarterRoleInTeam($target, $team, StarterRoleName::WorkspaceAccess->value);

        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            module: 'identity',
            action: 'user.login_lock',
            result: 'succeeded',
            source: 'ui',
            actorPublicId: (string) $admin->public_id,
            targetType: 'user',
            targetPublicId: (string) $otherTarget->public_id,
            teamPublicId: (string) $team->public_id,
            reason: 'Other security check '.Str::ulid(),
            security: true,
            securityCategory: 'identity',
        ));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/users/'.$target->public_id.'/impersonate', [
                'team_public_id' => $team->public_id,
                'reason' => 'Security history check',
            ])
            ->assertRedirect(route('dashboard'));

        $this->delete('/impersonation')->assertRedirect(route('admin.system-status'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/audit/security-history')
            ->assertOk()
            ->assertSee('Security Target')
            ->assertSee('security-target@example.test')
            ->assertSee('Other Security User')
            ->assertSee('other-security@example.test');

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/audit/security-history?user='.$target->public_id)
            ->assertOk()
            ->assertSee('impersonation.start')
            ->assertSee('Security history check')
            ->assertDontSee('Other security check')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Audit/SecurityHistory')
                ->where('filters.userPublicId', (string) $target->public_id));
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function adminActor(): array
    {
        $admin = User::factory()->create(['name' => 'Admin Actor']);
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($admin, $team, StarterRoleName::Administrator->value);

        return [$admin, $team];
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

    /**
     * @return array<string, string|int>
     */
    private function adminSession(Team $team): array
    {
        return [
            'active_team_public_id' => (string) $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
            AdministrativeSessionManager::ENTERED_AT => now()->toIso8601String(),
            AdministrativeSessionManager::LAST_ACTIVITY_AT => now()->toIso8601String(),
            AdministrativeSessionManager::HIGH_RISK_CONFIRMED_AT => now()->toIso8601String(),
        ];
    }
}
