<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ManagerHierarchyAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_team_scoped_manager_dag_with_history_audit_and_scopes(): void
    {
        $actor = User::factory()->create(['name' => 'Admin Actor']);
        $firstManager = User::factory()->create(['name' => 'First Manager']);
        $secondManager = User::factory()->create(['name' => 'Second Manager']);
        $teamLead = User::factory()->create(['name' => 'Team Lead']);
        $report = User::factory()->create(['name' => 'Report User']);
        $team = Team::query()->create(['name' => 'Collections']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        foreach ([$firstManager, $secondManager, $teamLead, $report] as $user) {
            $this->assignMembership($user, $team);
        }

        $session = $this->adminSession($team);

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/managers?team='.$team->public_id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Managers/Index')
                ->where('selectedTeamPublicId', $team->public_id)
            );

        $this->createRelationship($actor, $session, $team, $firstManager, $teamLead);
        $this->createRelationship($actor, $session, $team, $secondManager, $teamLead);
        $this->createRelationship($actor, $session, $team, $teamLead, $report);

        self::assertDatabaseHas(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS, [
            'team_id' => $team->id,
            'manager_user_id' => $firstManager->id,
            'report_user_id' => $teamLead->id,
            'valid_to' => null,
        ]);
        self::assertDatabaseHas(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS, [
            'team_id' => $team->id,
            'manager_user_id' => $secondManager->id,
            'report_user_id' => $teamLead->id,
            'valid_to' => null,
        ]);

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/managers', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $report->public_id,
                'report_user_public_id' => $firstManager->public_id,
                'valid_from' => now()->toDateString(),
                'reason' => 'Would close the graph.',
            ])
            ->assertSessionHasErrors('manager_user_public_id');

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/managers', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $report->public_id,
                'report_user_public_id' => $report->public_id,
                'valid_from' => now()->toDateString(),
                'reason' => 'Invalid self-management.',
            ])
            ->assertSessionHasErrors('manager_user_public_id');

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/managers/head', [
                'team_public_id' => $team->public_id,
                'user_public_id' => $firstManager->public_id,
                'head_manager' => true,
                'reason' => 'Regional lead.',
            ])
            ->assertRedirect(route('admin.managers.index', ['team' => $team->public_id]));

        /** @var ManagerHierarchy $hierarchy */
        $hierarchy = $this->app->make(ManagerHierarchy::class);
        $headScope = $hierarchy->scopeFor((string) $team->public_id, (string) $firstManager->public_id);
        $normalScope = $hierarchy->scopeFor((string) $team->public_id, (string) $secondManager->public_id);

        self::assertTrue($headScope->headManager);
        self::assertContains((string) $teamLead->public_id, $headScope->visibleUserPublicIds);
        self::assertContains((string) $report->public_id, $headScope->visibleUserPublicIds);
        self::assertFalse($normalScope->headManager);
        self::assertSame([(string) $teamLead->public_id], $normalScope->visibleUserPublicIds);

        $relationshipPublicId = DB::table(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $team->id)
            ->where('manager_user_id', $teamLead->id)
            ->where('report_user_id', $report->id)
            ->value('public_id');
        self::assertIsString($relationshipPublicId);

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/managers/'.$relationshipPublicId.'/end', [
                'team_public_id' => $team->public_id,
                'valid_to' => now()->toDateString(),
                'reason' => 'Reporting line changed.',
            ])
            ->assertRedirect(route('admin.managers.index', ['team' => $team->public_id]));

        $ended = DB::table(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS)->where('public_id', $relationshipPublicId)->first();
        self::assertIsObject($ended);
        self::assertNotNull(get_object_vars($ended)['valid_to'] ?? null);
        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'teams',
            'action' => 'team.manager_relationship.ended',
            'result' => 'succeeded',
            'actor_public_id' => $actor->public_id,
            'target_public_id' => $relationshipPublicId,
            'team_public_id' => $team->public_id,
        ]);
    }

    /**
     * @param  array<string, int|string>  $session
     */
    private function createRelationship(User $actor, array $session, Team $team, User $manager, User $report): void
    {
        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/managers', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $manager->public_id,
                'report_user_public_id' => $report->public_id,
                'valid_from' => now()->toDateString(),
                'reason' => 'Approved reporting line.',
            ])
            ->assertRedirect(route('admin.managers.index', ['team' => $team->public_id]));
    }

    private function assignStarterRoleInTeam(User $user, Team $team, string $roleName): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $this->assignMembership($user, $team);

        DB::table(DatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    private function assignMembership(User $user, Team $team): void
    {
        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insertOrIgnore([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function adminSession(Team $team): array
    {
        return [
            'active_team_public_id' => $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
            'atlas_admin_mode_entered_at' => now()->toIso8601String(),
            'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
        ];
    }
}
