<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
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
            metadata: ['ticket' => 'SUP-100'],
        ));

        self::assertDatabaseHas('audit_events', [
            'module' => 'identity',
            'action' => 'user.mfa_reset',
            'result' => 'succeeded',
            'source' => 'admin',
            'actor_public_id' => $actor->public_id,
            'target_public_id' => $target->public_id,
            'reason' => 'Support verified identity.',
            'is_security' => true,
        ]);
        self::assertDatabaseHas('audit_security_events', [
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

        $publicId = DB::table('audit_events')->value('public_id');
        self::assertIsString($publicId);

        $this->expectException(QueryException::class);

        DB::table('audit_events')
            ->where('public_id', $publicId)
            ->update(['result' => 'tampered']);
    }

    public function test_logout_session_change_is_audited(): void
    {
        $actor = User::factory()->create();

        event(new Logout('web', $actor));

        self::assertDatabaseHas('audit_events', [
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
            securityCategory: 'authorization',
        ));

        $this->actingAs($actor)
            ->withSession([
                'active_team_public_id' => $activeTeam->public_id,
                'auth.password_confirmed_at' => now()->unix(),
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

    private function assignStarterRoleInTeam(User $user, Team $team): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $role = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();

        DB::table('team_user_assignments')->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }
}
