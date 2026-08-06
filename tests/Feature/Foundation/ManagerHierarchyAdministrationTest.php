<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Audit\Application\Public\Persistence\AuditDatabaseTable;
use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use Illuminate\Contracts\Support\Arrayable;
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
        $extraReport = User::factory()->create(['name' => 'Extra Report']);
        $team = Team::query()->create(['name' => 'Collections']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        foreach ([$firstManager, $secondManager, $teamLead, $report, $extraReport] as $user) {
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

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/managers/create?team='.$team->public_id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Managers/Create')
                ->where('selectedTeamPublicId', $team->public_id)
                ->where('selectedManagerPublicId', '')
                ->where('manager', null)
                ->has('teamMembers', 6)
            );

        $this->createRelationship($actor, $session, $team, $firstManager, $teamLead);
        $this->createRelationship($actor, $session, $team, $secondManager, $teamLead);
        $this->createRelationship($actor, $session, $team, $teamLead, $report);

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/managers?team='.$team->public_id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Managers/Index')
                ->where('selectedTeamPublicId', $team->public_id)
                ->has('managers', 3)
                ->where('table.key', 'admin.managers')
                ->where('table.state.filters.team', $team->public_id)
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/managers?team='.$team->public_id.'&type=regular&directReports=with&subtreeReports=with')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Managers/Index')
                ->where('table.state.filters.type', 'regular')
                ->where('table.state.filters.directReports', 'with')
                ->where('table.state.filters.subtreeReports', 'with')
                ->where('managers', fn ($managers): bool => $this->nonEmptyEveryRow($managers,
                    fn (array $manager): bool => ($manager['managerType'] ?? null) === 'regular'
                        && ($manager['directReportsCount'] ?? 0) > 0
                        && ($manager['subtreeReportsCount'] ?? 0) > 0
                ))
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/managers/create?team='.$team->public_id.'&preview_manager='.$firstManager->public_id.'&preview_reports%5B%5D='.$report->public_id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Managers/Create')
                ->where('selectedManagerPublicId', $firstManager->public_id)
                ->where('manager.userPublicId', $firstManager->public_id)
                ->has('relationships', 1)
                ->has('tree', 1)
                ->where('previewReportPublicIds', [$report->public_id])
                ->has('assignmentPreviews', 1)
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/managers/'.$firstManager->public_id.'/edit?team='.$team->public_id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Managers/Edit')
                ->where('selectedTeamPublicId', $team->public_id)
                ->where('manager.userPublicId', $firstManager->public_id)
                ->where('manager.managerType', 'regular')
                ->has('relationships', 1)
                ->has('tree', 1)
                ->has('previewReportPublicIds', 0)
                ->has('assignmentPreviews', 0)
            );

        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, [
            'team_id' => $team->id,
            'manager_user_id' => $firstManager->id,
            'report_user_id' => $teamLead->id,
            'valid_to' => null,
        ]);
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, [
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
            ->assertRedirect(route('admin.managers.edit', ['user' => $firstManager->public_id, 'team' => $team->public_id]));

        /** @var ManagerHierarchy $hierarchy */
        $hierarchy = $this->app->make(ManagerHierarchy::class);
        $headScope = $hierarchy->scopeFor((string) $team->public_id, (string) $firstManager->public_id);
        $normalScope = $hierarchy->scopeFor((string) $team->public_id, (string) $secondManager->public_id);

        self::assertTrue($headScope->headManager);
        self::assertContains((string) $teamLead->public_id, $headScope->visibleUserPublicIds);
        self::assertContains((string) $report->public_id, $headScope->visibleUserPublicIds);
        self::assertFalse($normalScope->headManager);
        self::assertSame([(string) $teamLead->public_id], $normalScope->visibleUserPublicIds);

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/managers', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $secondManager->public_id,
                'report_user_public_ids' => [$report->public_id, $extraReport->public_id],
                'valid_from' => now()->toDateString(),
                'reason' => 'Approved reporting expansion.',
            ])
            ->assertRedirect(route('admin.managers.edit', ['user' => $secondManager->public_id, 'team' => $team->public_id]));

        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, [
            'team_id' => $team->id,
            'manager_user_id' => $secondManager->id,
            'report_user_id' => $extraReport->id,
            'valid_to' => null,
        ]);

        $relationshipPublicId = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
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
            ->assertRedirect(route('admin.managers.edit', ['user' => $teamLead->public_id, 'team' => $team->public_id]));

        $ended = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)->where('public_id', $relationshipPublicId)->first();
        self::assertIsObject($ended);
        self::assertNotNull(get_object_vars($ended)['valid_to'] ?? null);
        self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, [
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
            ->assertRedirect(route('admin.managers.edit', ['user' => $manager->public_id, 'team' => $team->public_id]));
    }

    private function assignStarterRoleInTeam(User $user, Team $team, string $roleName): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $this->assignMembership($user, $team);

        DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    private function assignMembership(User $user, Team $team): void
    {
        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insertOrIgnore([
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

    /**
     * @param  callable(array<string, mixed>): bool  $predicate
     */
    private function nonEmptyEveryRow(mixed $rows, callable $predicate): bool
    {
        $rows = self::listValue($rows);

        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (! is_array($row) || ! $predicate(self::stringKeyedArray($row))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<mixed>
     */
    private static function listValue(mixed $value): array
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if ($value instanceof \Traversable) {
            return array_values(iterator_to_array($value));
        }

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
