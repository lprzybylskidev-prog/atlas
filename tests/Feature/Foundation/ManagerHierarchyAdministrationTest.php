<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Audit\Infrastructure\Persistence\TableNames\AuditDatabaseTable;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Exceptions\ManagerHierarchyViolation;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Infrastructure\Persistence\DatabaseManagerHierarchy;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
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

        $this->actingAs($actor)->withSession($session)->get('/admin/managers')->assertNotFound();

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/teams/'.$team->public_id.'/structure')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Structure')
                ->where('selectedTeamPublicId', $team->public_id)
                ->has('teamMembers', 6)
                ->has('activeRelationships', 0)
                ->where('structureVersion', fn (mixed $version): bool => is_string($version) && strlen($version) === 64)
            );

        $this->createRelationship($actor, $session, $team, $firstManager, $teamLead);
        /** @var ManagerHierarchy $hierarchy */
        $hierarchy = $this->app->make(ManagerHierarchy::class);
        $staleVersion = $hierarchy->version((string) $team->public_id);
        $this->createRelationship($actor, $session, $team, $secondManager, $teamLead);
        $this->createRelationship($actor, $session, $team, $teamLead, $report);

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/teams/'.$team->public_id.'/structure/relationships', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $firstManager->public_id,
                'report_user_public_id' => $teamLead->public_id,
                'valid_from' => now()->toDateString(),
                'reason' => 'Duplicate active relationship.',
                'structure_version' => $hierarchy->version((string) $team->public_id),
            ])
            ->assertSessionHasErrors([
                'manager_user_public_id' => __('validation.custom.manager_hierarchy.duplicate_active_relationship'),
            ]);

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/teams/'.$team->public_id.'/structure/relationships', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $firstManager->public_id,
                'report_user_public_id' => $extraReport->public_id,
                'valid_from' => now()->toDateString(),
                'reason' => 'Stale structure attempt.',
                'structure_version' => $staleVersion,
            ])
            ->assertSessionHasErrors('manager_user_public_id');

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/teams/'.$team->public_id.'/structure')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Structure')
                ->where('teamMembers', fn (Collection $members): bool => $members->contains(
                    fn (mixed $member): bool => is_array($member)
                        && ($member['value'] ?? null) === $firstManager->public_id
                        && ($member['manager'] ?? null) === true,
                ) && $members->contains(
                    fn (mixed $member): bool => is_array($member)
                        && ($member['value'] ?? null) === $report->public_id
                        && ($member['manager'] ?? null) === false,
                ))
                ->has('activeRelationships', 3)
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/teams/'.$team->public_id.'/structure?role_preview_user='.$firstManager->public_id.'&role_preview_target=head_manager')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('structuralRolePreview.allowed', true)
                ->where('structuralRolePreview.currentRole', 'manager')
                ->where('structuralRolePreview.targetRole', 'head_manager')
                ->has('structuralRolePreview.endingRelationshipPublicIds', 1)
                ->has('structuralRolePreview.affectedUserPublicIds', 1)
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
            ->post('/admin/teams/'.$team->public_id.'/structure/relationships', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $report->public_id,
                'report_user_public_id' => $firstManager->public_id,
                'valid_from' => now()->toDateString(),
                'reason' => 'Would close the graph.',
            ])
            ->assertSessionHasErrors('manager_user_public_id');

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/teams/'.$team->public_id.'/structure/relationships', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $report->public_id,
                'report_user_public_id' => $report->public_id,
                'valid_from' => now()->toDateString(),
                'reason' => 'Invalid self-management.',
            ])
            ->assertSessionHasErrors('manager_user_public_id');

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/teams/'.$team->public_id.'/structure/structural-role', [
                'team_public_id' => $team->public_id,
                'user_public_id' => $firstManager->public_id,
                'structural_role' => 'head_manager',
                'reason' => 'Regional lead.',
            ])
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id]));

        $headScope = $hierarchy->scopeFor((string) $team->public_id, (string) $firstManager->public_id);
        $normalScope = $hierarchy->scopeFor((string) $team->public_id, (string) $secondManager->public_id);

        self::assertTrue($headScope->headManager);
        self::assertContains((string) $teamLead->public_id, $headScope->visibleUserPublicIds);
        self::assertContains((string) $report->public_id, $headScope->visibleUserPublicIds);
        self::assertFalse($normalScope->headManager);
        self::assertSame([(string) $teamLead->public_id], $normalScope->visibleUserPublicIds);

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/teams/'.$team->public_id.'/structure/structural-role', [
                'team_public_id' => $team->public_id,
                'user_public_id' => $firstManager->public_id,
                'structural_role' => 'manager',
                'reason' => 'Attempt to remove the last head manager.',
                'structure_version' => $hierarchy->version((string) $team->public_id),
            ])
            ->assertSessionHasErrors('user_public_id');

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/teams/'.$team->public_id.'/structure/relationships', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $secondManager->public_id,
                'report_user_public_ids' => [$report->public_id, $extraReport->public_id],
                'valid_from' => now()->toDateString(),
                'reason' => 'Approved reporting expansion.',
            ])
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id]));

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
            ->patch('/admin/teams/'.$team->public_id.'/structure/relationships/'.$relationshipPublicId.'/end', [
                'team_public_id' => $team->public_id,
                'valid_to' => now()->toDateString(),
                'reason' => 'Reporting line changed.',
            ])
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id]));

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

    public function test_team_structure_owns_membership_changes_and_preserves_effective_dated_history(): void
    {
        $actor = User::factory()->create();
        $member = User::factory()->create(['name' => 'History Member']);
        $team = Team::query()->create(['name' => 'History Team']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);
        $session = $this->adminSession($team);

        $this->actingAs($actor)->withSession($session)
            ->post('/admin/teams/'.$team->public_id.'/structure/members', ['user_public_id' => $member->public_id])
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id]));
        $this->actingAs($actor)->withSession($session)
            ->delete('/admin/teams/'.$team->public_id.'/structure/members/'.$member->public_id, ['reason' => 'Assignment completed.'])
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id]));
        $this->actingAs($actor)->withSession($session)
            ->post('/admin/teams/'.$team->public_id.'/structure/members', ['user_public_id' => $member->public_id])
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id]));

        self::assertSame(2, DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $team->id)
            ->where('user_id', $member->id)
            ->count());
        self::assertSame(1, DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $team->id)
            ->where('user_id', $member->id)
            ->whereNull('valid_to')
            ->count());

        $this->actingAs($actor)->withSession($session)
            ->get('/admin/teams/'.$team->public_id.'/structure')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('teamMembers', 2)
                ->where('membershipHistory', fn (Collection $history): bool => $history->where('userPublicId', $member->public_id)->count() === 2
                    && $history->where('userPublicId', $member->public_id)->where('active', true)->count() === 1
                    && $history->where('userPublicId', $member->public_id)->where('active', false)->count() === 1)
                ->has('assignableUsers')
            );

        self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, ['action' => 'team.user_access_added', 'result' => 'succeeded']);
        self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, ['action' => 'team.user_access_removed', 'result' => 'succeeded']);
    }

    public function test_rejected_destructive_operations_return_contextual_errors_and_leave_history_unchanged(): void
    {
        $actor = User::factory()->create(['name' => 'Removal Actor']);
        $manager = User::factory()->create(['name' => 'Removal Manager']);
        $report = User::factory()->create(['name' => 'Removal Report']);
        $team = Team::query()->create(['name' => 'Removal Safety Team']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);
        $this->assignMembership($manager, $team);
        $this->assignMembership($report, $team);
        $session = $this->adminSession($team);
        $hierarchy = $this->app->make(ManagerHierarchy::class);
        $this->ensureManagerRole($actor, $team, $manager);
        $hierarchy->assign(
            (string) $actor->public_id,
            (string) $team->public_id,
            (string) $manager->public_id,
            (string) $report->public_id,
            now()->toDateString(),
            'Relationship that blocks membership removal.',
        );
        $relationshipPublicId = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $team->id)
            ->where('manager_user_id', $manager->id)
            ->where('report_user_id', $report->id)
            ->value('public_id');
        self::assertIsString($relationshipPublicId);

        $this->actingAs($actor)
            ->withSession($session)
            ->delete('/admin/teams/'.$team->public_id.'/structure/members/'.$manager->public_id, [
                'reason' => 'Attempt while the Manager relationship is active.',
            ])
            ->assertSessionHasErrors('operation');

        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS, [
            'team_id' => $team->id,
            'user_id' => $manager->id,
            'valid_to' => null,
        ]);
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, [
            'public_id' => $relationshipPublicId,
            'valid_to' => null,
        ]);
        self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, [
            'action' => 'team.user_access_remove_rejected',
            'result' => 'rejected',
            'target_public_id' => $manager->public_id,
            'team_public_id' => $team->public_id,
        ]);

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/teams/'.$team->public_id.'/structure/relationships/'.$relationshipPublicId.'/end', [
                'team_public_id' => $team->public_id,
                'valid_to' => now()->toDateString(),
                'reason' => 'x',
            ])
            ->assertSessionHasErrors('reason');
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, [
            'public_id' => $relationshipPublicId,
            'valid_to' => null,
        ]);

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/teams/'.$team->public_id.'/structure/relationships/'.$relationshipPublicId.'/end', [
                'team_public_id' => $team->public_id,
                'valid_to' => now()->toDateString(),
                'reason' => 'Approved relationship removal.',
            ])
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id]));
        $validTo = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('public_id', $relationshipPublicId)
            ->value('valid_to');
        self::assertNotNull($validTo);

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/teams/'.$team->public_id.'/structure/relationships/'.$relationshipPublicId.'/end', [
                'team_public_id' => $team->public_id,
                'valid_to' => now()->addDay()->toDateString(),
                'reason' => 'Duplicate relationship removal attempt.',
            ])
            ->assertSessionHasErrors('operation');

        self::assertSame(1, DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('public_id', $relationshipPublicId)
            ->where('valid_to', $validTo)
            ->count());
        self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, [
            'action' => 'team.manager_relationship.ended',
            'result' => 'succeeded',
            'target_public_id' => $relationshipPublicId,
        ]);
    }

    public function test_structural_role_transitions_are_explicit_atomic_audited_and_scope_head_managers_to_the_whole_team(): void
    {
        $actor = User::factory()->create(['name' => 'Structure Actor']);
        $manager = User::factory()->create(['name' => 'Structural Manager']);
        $parentManager = User::factory()->create(['name' => 'Parent Manager']);
        $firstReport = User::factory()->create(['name' => 'First Report']);
        $secondReport = User::factory()->create(['name' => 'Second Report']);
        $unrelated = User::factory()->create(['name' => 'Unrelated Member']);
        $team = Team::query()->create(['name' => 'Structural Roles Team']);

        foreach ([$actor, $manager, $parentManager, $firstReport, $secondReport, $unrelated] as $member) {
            $this->assignMembership($member, $team);
        }

        $hierarchy = $this->app->make(ManagerHierarchy::class);
        $this->ensureManagerRole($actor, $team, $manager);
        $this->ensureManagerRole($actor, $team, $parentManager);
        $hierarchy->assign((string) $actor->public_id, (string) $team->public_id, (string) $parentManager->public_id, (string) $manager->public_id, now()->toDateString(), 'Manager reports to another manager.');
        $hierarchy->assign((string) $actor->public_id, (string) $team->public_id, (string) $manager->public_id, (string) $firstReport->public_id, now()->toDateString(), 'First direct report.');
        $hierarchy->assign((string) $actor->public_id, (string) $team->public_id, (string) $manager->public_id, (string) $secondReport->public_id, now()->toDateString(), 'Second direct report.');

        $preview = $hierarchy->previewStructuralRoleChange((string) $team->public_id, (string) $manager->public_id, 'head_manager');
        self::assertTrue($preview->allowed);
        self::assertSame('manager', $preview->currentRole);
        self::assertSame('head_manager', $preview->targetRole);
        self::assertCount(3, $preview->endingRelationshipPublicIds);
        self::assertEqualsCanonicalizing([
            (string) $parentManager->public_id,
            (string) $firstReport->public_id,
            (string) $secondReport->public_id,
        ], $preview->affectedUserPublicIds);

        $version = $hierarchy->version((string) $team->public_id);
        $hierarchy->changeStructuralRole(
            (string) $actor->public_id,
            (string) $team->public_id,
            (string) $manager->public_id,
            'head_manager',
            'Manage the complete team.',
            $version,
        );

        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS, [
            'team_id' => $team->id,
            'user_id' => $manager->id,
            'structural_role' => 'head_manager',
        ]);
        self::assertSame(0, DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $team->id)
            ->where(static fn (Builder $query) => $query->where('manager_user_id', $manager->id)->orWhere('report_user_id', $manager->id))
            ->whereNull('valid_to')
            ->count());
        self::assertSame(3, DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->whereIn('public_id', $preview->endingRelationshipPublicIds)
            ->whereNotNull('valid_to')
            ->where('end_reason', 'Manage the complete team.')
            ->count());

        $scope = $hierarchy->scopeFor((string) $team->public_id, (string) $manager->public_id);
        self::assertTrue($scope->headManager);
        self::assertEqualsCanonicalizing([
            (string) $actor->public_id,
            (string) $manager->public_id,
            (string) $parentManager->public_id,
            (string) $firstReport->public_id,
            (string) $secondReport->public_id,
            (string) $unrelated->public_id,
        ], $scope->visibleUserPublicIds);
        self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, [
            'module' => 'teams',
            'action' => 'team.structural_role.changed',
            'result' => 'succeeded',
            'actor_public_id' => $actor->public_id,
            'target_public_id' => $manager->public_id,
            'team_public_id' => $team->public_id,
        ]);

        try {
            $hierarchy->assign(
                (string) $actor->public_id,
                (string) $team->public_id,
                (string) $manager->public_id,
                (string) $unrelated->public_id,
                now()->toDateString(),
                'Head Manager cannot own a normal edge.',
            );
            self::fail('A Head Manager cannot own normal relationships.');
        } catch (ManagerHierarchyViolation $exception) {
            self::assertStringContainsString('structural Manager', $exception->getMessage());
        }

        try {
            $hierarchy->assign(
                (string) $actor->public_id,
                (string) $team->public_id,
                (string) $parentManager->public_id,
                (string) $manager->public_id,
                now()->toDateString(),
                'Head Manager cannot report to a normal manager.',
            );
            self::fail('A Head Manager cannot be a normal report.');
        } catch (ManagerHierarchyViolation $exception) {
            self::assertStringContainsString('Head Manager', $exception->getMessage());
        }
    }

    public function test_manager_employee_transitions_preserve_incoming_edges_end_outgoing_edges_and_protect_the_last_head_manager(): void
    {
        $actor = User::factory()->create();
        $parent = User::factory()->create();
        $manager = User::factory()->create();
        $report = User::factory()->create();
        $secondHead = User::factory()->create();
        $team = Team::query()->create(['name' => 'Transition Invariants Team']);

        foreach ([$actor, $parent, $manager, $report, $secondHead] as $member) {
            $this->assignMembership($member, $team);
        }

        $hierarchy = $this->app->make(ManagerHierarchy::class);
        $this->ensureManagerRole($actor, $team, $parent);
        $this->ensureManagerRole($actor, $team, $manager);
        $hierarchy->assign((string) $actor->public_id, (string) $team->public_id, (string) $parent->public_id, (string) $manager->public_id, now()->toDateString(), 'Incoming manager edge.');
        $hierarchy->assign((string) $actor->public_id, (string) $team->public_id, (string) $manager->public_id, (string) $report->public_id, now()->toDateString(), 'Outgoing manager edge.');

        $hierarchy->changeStructuralRole((string) $actor->public_id, (string) $team->public_id, (string) $manager->public_id, 'employee', 'Return to Employee.');

        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS, ['team_id' => $team->id, 'user_id' => $manager->id, 'structural_role' => 'employee']);
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, ['team_id' => $team->id, 'manager_user_id' => $parent->id, 'report_user_id' => $manager->id, 'valid_to' => null]);
        self::assertDatabaseMissing(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, ['team_id' => $team->id, 'manager_user_id' => $manager->id, 'report_user_id' => $report->id, 'valid_to' => null]);

        $hierarchy->changeStructuralRole((string) $actor->public_id, (string) $team->public_id, (string) $manager->public_id, 'manager', 'Return to Manager without restoring reports.');
        self::assertSame([], $hierarchy->scopeFor((string) $team->public_id, (string) $manager->public_id)->visibleUserPublicIds);
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, ['team_id' => $team->id, 'manager_user_id' => $parent->id, 'report_user_id' => $manager->id, 'valid_to' => null]);

        $hierarchy->changeStructuralRole((string) $actor->public_id, (string) $team->public_id, (string) $manager->public_id, 'head_manager', 'First required Head Manager.');

        try {
            $hierarchy->changeStructuralRole((string) $actor->public_id, (string) $team->public_id, (string) $manager->public_id, 'employee', 'Invalid last Head Manager removal.');
            self::fail('The last required Head Manager must be protected.');
        } catch (ManagerHierarchyViolation $exception) {
            self::assertStringContainsString('last active head manager', $exception->getMessage());
        }

        $hierarchy->changeStructuralRole((string) $actor->public_id, (string) $team->public_id, (string) $secondHead->public_id, 'head_manager', 'Second required Head Manager.');
        $hierarchy->changeStructuralRole((string) $actor->public_id, (string) $team->public_id, (string) $manager->public_id, 'employee', 'A second Head Manager now exists.');
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS, ['team_id' => $team->id, 'user_id' => $manager->id, 'structural_role' => 'employee']);
        self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, ['action' => 'team.structural_role.change_rejected', 'result' => 'rejected', 'target_public_id' => $manager->public_id]);
    }

    public function test_structural_role_change_rejects_stale_or_reasonless_writes_and_rolls_back_when_audit_fails(): void
    {
        $actor = User::factory()->create();
        $manager = User::factory()->create();
        $report = User::factory()->create();
        $team = Team::query()->create(['name' => 'Structural Role Audit Team']);

        foreach ([$actor, $manager, $report] as $member) {
            $this->assignMembership($member, $team);
        }

        $hierarchy = $this->app->make(ManagerHierarchy::class);
        $staleVersion = $hierarchy->version((string) $team->public_id);
        $this->ensureManagerRole($actor, $team, $manager);
        $hierarchy->assign((string) $actor->public_id, (string) $team->public_id, (string) $manager->public_id, (string) $report->public_id, now()->toDateString(), 'Atomic cleanup edge.');

        foreach ([
            ['', null],
            ['Valid reason with stale version.', $staleVersion],
        ] as [$reason, $version]) {
            try {
                $hierarchy->changeStructuralRole(
                    (string) $actor->public_id,
                    (string) $team->public_id,
                    (string) $manager->public_id,
                    'employee',
                    (string) $reason,
                    is_string($version) ? $version : null,
                );
                self::fail('The invalid structural-role write should be rejected.');
            } catch (ManagerHierarchyViolation) {
                self::assertDatabaseHas(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS, ['team_id' => $team->id, 'user_id' => $manager->id, 'structural_role' => 'manager']);
                self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, ['team_id' => $team->id, 'manager_user_id' => $manager->id, 'report_user_id' => $report->id, 'valid_to' => null]);
            }
        }

        $failingHierarchy = new DatabaseManagerHierarchy(
            new class implements AuditRecorder
            {
                public function record(AuditEvent $event): void
                {
                    throw new RuntimeException('Structural role audit unavailable.');
                }
            },
            $this->app->make(UserLookup::class),
        );

        try {
            $failingHierarchy->changeStructuralRole(
                (string) $actor->public_id,
                (string) $team->public_id,
                (string) $manager->public_id,
                'employee',
                'Audit must commit with cleanup.',
                $failingHierarchy->version((string) $team->public_id),
            );
            self::fail('Mandatory audit failure must roll back the structural-role transition.');
        } catch (RuntimeException $exception) {
            self::assertSame('Structural role audit unavailable.', $exception->getMessage());
        }

        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS, ['team_id' => $team->id, 'user_id' => $manager->id, 'structural_role' => 'manager']);
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, ['team_id' => $team->id, 'manager_user_id' => $manager->id, 'report_user_id' => $report->id, 'valid_to' => null]);
    }

    /**
     * @param  array<string, int|string>  $session
     */
    private function createRelationship(User $actor, array $session, Team $team, User $manager, User $report): void
    {
        $this->ensureManagerRole($actor, $team, $manager);

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/teams/'.$team->public_id.'/structure/relationships', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $manager->public_id,
                'report_user_public_id' => $report->public_id,
                'valid_from' => now()->toDateString(),
                'reason' => 'Approved reporting line.',
            ])
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id]));
    }

    private function ensureManagerRole(User $actor, Team $team, User $manager): void
    {
        $hierarchy = $this->app->make(ManagerHierarchy::class);
        $preview = $hierarchy->previewStructuralRoleChange((string) $team->public_id, (string) $manager->public_id, 'manager');

        if ($preview->currentRole !== 'manager') {
            $hierarchy->changeStructuralRole(
                (string) $actor->public_id,
                (string) $team->public_id,
                (string) $manager->public_id,
                'manager',
                'Prepare the structural Manager role.',
            );
        }
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
}
