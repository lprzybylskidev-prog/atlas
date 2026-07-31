<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AuditFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_audit_contract_writes_full_audit_records_without_legacy_table(): void
    {
        self::assertFalse(Schema::hasTable('security_audit_events'));

        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->app->make(SecurityAuditRecorder::class)->record(new SecurityAuditEvent(
            module: 'identity',
            action: 'user.mfa_reset',
            result: 'succeeded',
            source: 'admin',
            actorPublicId: $actor->public_id,
            targetPublicId: $target->public_id,
            reason: 'Support verified identity.',
            category: SecurityAuditCategory::Mfa,
            metadata: ['ticket' => 'SUP-100'],
        ));

        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'identity',
            'action' => 'user.mfa_reset',
            'result' => 'succeeded',
            'source' => 'admin',
            'actor_public_id' => $actor->public_id,
            'target_public_id' => $target->public_id,
            'reason' => 'Support verified identity.',
            'is_security' => true,
        ]);
        self::assertDatabaseHas(DatabaseTable::AUDIT_SECURITY_EVENTS, [
            'category' => 'mfa',
            'action' => 'user.mfa_reset',
            'result' => 'succeeded',
            'actor_public_id' => $actor->public_id,
            'target_public_id' => $target->public_id,
        ]);
    }

    public function test_audit_records_are_append_only(): void
    {
        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            module: 'tests',
            action: 'audit.append_only_probe',
            result: 'succeeded',
            source: 'test',
        ));

        $publicId = DB::table(DatabaseTable::AUDIT_EVENTS)->value('public_id');
        self::assertIsString($publicId);

        $this->expectException(QueryException::class);

        DB::table(DatabaseTable::AUDIT_EVENTS)
            ->where('public_id', $publicId)
            ->update(['result' => 'tampered']);
    }

    public function test_audit_can_be_recorded_without_http_session_context(): void
    {
        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            module: 'tests',
            action: 'audit.no_request_context_probe',
            result: 'succeeded',
            source: 'cli',
        ));

        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'tests',
            'action' => 'audit.no_request_context_probe',
            'result' => 'succeeded',
            'source' => 'cli',
            'actual_actor_public_id' => null,
            'impersonated_user_public_id' => null,
            'impersonation_session_id' => null,
        ]);
    }

    public function test_audit_recorder_enriches_impersonation_context_without_event_duplication(): void
    {
        Route::post('/admin/testing/audit-impersonation-context', function (): Response {
            app(AuditRecorder::class)->record(new AuditEvent(
                module: 'tests',
                action: 'audit.impersonated_action_probe',
                result: 'succeeded',
                source: 'ui',
            ));

            return response()->noContent();
        })->middleware('web');

        $actor = User::factory()->create();
        $target = User::factory()->create();
        $sessionId = '01K0DXCKWJ3N8B6N7VHQ0A9999';
        $requestId = 'audit-context-request';

        $this->actingAs($actor)
            ->withHeader('X-Request-Id', $requestId)
            ->withSession([
                ImpersonationManager::SESSION_ID => $sessionId,
                ImpersonationManager::ACTOR_PUBLIC_ID => (string) $actor->public_id,
                ImpersonationManager::USER_PUBLIC_ID => (string) $target->public_id,
            ])
            ->post('/admin/testing/audit-impersonation-context')
            ->assertNoContent();

        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'tests',
            'action' => 'audit.impersonated_action_probe',
            'actual_actor_public_id' => $actor->public_id,
            'impersonated_user_public_id' => $target->public_id,
            'impersonation_session_id' => $sessionId,
            'correlation_id' => $requestId,
        ]);
    }

    public function test_security_audit_events_require_explicit_category(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Security audit events require an explicit security category.');

        new AuditEvent(
            module: 'tests',
            action: 'audit.security_without_category',
            result: 'succeeded',
            source: 'test',
            security: true,
        );
    }

    public function test_non_security_audit_events_reject_security_category(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only security audit events may define a security category.');

        new AuditEvent(
            module: 'tests',
            action: 'audit.non_security_with_category',
            result: 'succeeded',
            source: 'test',
            securityCategory: SecurityAuditCategory::Security,
        );
    }

    public function test_logout_session_change_is_audited(): void
    {
        $actor = User::factory()->create();

        event(new Logout('web', $actor));

        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'identity',
            'action' => 'auth.logout',
            'result' => 'succeeded',
            'actor_public_id' => $actor->public_id,
            'target_public_id' => $actor->public_id,
            'is_security' => true,
        ]);
    }

    public function test_admin_can_browse_audit_read_model(): void
    {
        $actor = User::factory()->create();
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam);

        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            module: 'authorization',
            action: 'authorization.role_updated',
            result: 'succeeded',
            source: 'admin',
            actorPublicId: $actor->public_id,
            targetType: 'role',
            targetPublicId: (string) Str::ulid(),
            teamPublicId: $activeTeam->public_id,
            before: ['permissions' => ['dashboard']],
            after: ['permissions' => ['dashboard', 'admin.audit.index']],
            security: true,
            securityCategory: SecurityAuditCategory::Authorization,
        ));

        $this->actingAs($actor)
            ->withSession([
                'active_team_public_id' => $activeTeam->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/audit?search=role_updated')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Audit/Index')
                ->where('table.key', 'admin.audit')
                ->where('table.pagination.total', 1)
                ->where('events.0.action', 'authorization.role_updated')
                ->where('events.0.security', true)
            );
    }

    public function test_admin_audit_browser_exposes_select_filters_and_view_state_filters(): void
    {
        $actor = User::factory()->create();
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $otherTeam = Team::query()->create(['name' => 'Legal']);
        $this->assignStarterRoleInTeam($actor, $activeTeam);

        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            module: 'authorization',
            action: 'authorization.role_updated',
            result: 'succeeded',
            source: 'admin',
            actorPublicId: $actor->public_id,
            targetType: 'role',
            targetPublicId: (string) Str::ulid(),
            teamPublicId: $activeTeam->public_id,
            security: true,
            securityCategory: SecurityAuditCategory::Authorization,
        ));
        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            module: 'identity',
            action: 'auth.login',
            result: 'failed',
            source: 'web',
            actorPublicId: (string) Str::ulid(),
            targetType: 'user',
            targetPublicId: (string) Str::ulid(),
            teamPublicId: $otherTeam->public_id,
            security: true,
            securityCategory: SecurityAuditCategory::Authentication,
        ));

        $this->actingAs($actor)
            ->withSession([
                'active_team_public_id' => $activeTeam->public_id,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get(sprintf(
                '/admin/audit?module=authorization&action=authorization.role_updated&source=admin&target_type=role&team=%s&result=succeeded&security=yes',
                $activeTeam->public_id,
            ))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Audit/Index')
                ->where('table.pagination.total', 1)
                ->where('events.0.module', 'authorization')
                ->where('events.0.action', 'authorization.role_updated')
                ->where('table.state.filters.module', 'authorization')
                ->where('table.state.filters.action', 'authorization.role_updated')
                ->where('table.state.filters.source', 'admin')
                ->where('table.state.filters.target_type', 'role')
                ->where('table.state.filters.team', $activeTeam->public_id)
                ->where('table.state.filters.result', 'succeeded')
                ->where('table.state.filters.security', 'yes')
                ->where('filterOptions.modules.0.value', 'authorization')
                ->where('filterOptions.actions.0.value', 'auth.login')
                ->where('filterOptions.sources.0.value', 'admin')
                ->where('filterOptions.targetTypes.0.value', 'role')
                ->where('filterOptions.teams.1.value', $activeTeam->public_id)
            );
    }

    public function test_admin_can_filter_security_history_and_open_impersonation_session_detail(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam);
        $sessionId = 'test-security-history-session';

        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            module: 'identity',
            action: 'impersonation.start',
            result: 'succeeded',
            source: 'admin',
            actorPublicId: $actor->public_id,
            actualActorPublicId: $actor->public_id,
            impersonatedUserPublicId: $target->public_id,
            impersonationSessionId: $sessionId,
            targetType: 'user',
            targetPublicId: $target->public_id,
            teamPublicId: $activeTeam->public_id,
            reason: 'Support verified identity.',
            security: true,
            securityCategory: SecurityAuditCategory::Impersonation,
        ));
        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            module: 'identity',
            action: 'auth.login',
            result: 'rejected',
            source: 'web',
            actorPublicId: $target->public_id,
            targetType: 'user',
            targetPublicId: $target->public_id,
            teamPublicId: $activeTeam->public_id,
            reason: 'Rate limit blocked login.',
            security: true,
            securityCategory: SecurityAuditCategory::Authentication,
        ));

        $session = [
            'active_team_public_id' => $activeTeam->public_id,
            'auth.password_confirmed_at' => now()->unix(),
            'atlas_admin_mode_entered_at' => now()->toIso8601String(),
            'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
        ];

        $this->actingAs($actor)
            ->withSession($session)
            ->get(sprintf('/admin/audit/security-history?user=%s&action=impersonation.start&result=succeeded&source=admin', $target->public_id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Audit/SecurityHistory')
                ->where('table.key', 'admin.audit.security-history')
                ->where('table.pagination.total', 1)
                ->where('table.state.filters.user', $target->public_id)
                ->where('table.state.filters.action', 'impersonation.start')
                ->where('table.state.filters.result', 'succeeded')
                ->where('table.state.filters.source', 'admin')
                ->where('summary.visible', 1)
                ->where('summary.impersonated', 1)
                ->where('events.0.impersonationSessionId', $sessionId)
                ->where('filterOptions.actions.1.value', 'impersonation.start'));

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/audit/impersonation/'.$sessionId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Audit/ImpersonationSession')
                ->where('session.id', $sessionId)
                ->where('session.operationCount', 1)
                ->where('session.securityCount', 1)
                ->where('table.key', 'admin.audit.impersonation-session-events')
                ->where('table.state.filters.session', $sessionId)
                ->has('events', 1));
    }

    private function assignStarterRoleInTeam(User $user, Team $team): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

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
    }
}
