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
use App\Modules\Core\Teams\Application\Public\Contracts\TeamStructureMutationGuard;
use App\Modules\Core\Teams\Infrastructure\Persistence\DatabaseManagerHierarchy;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
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
                ->where('selectedManagerPublicId', '')
                ->where('manager', null)
                ->has('teamMembers', 6)
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
                'report_user_public_id' => $extraReport->public_id,
                'valid_from' => now()->toDateString(),
                'reason' => 'Stale structure attempt.',
                'structure_version' => $staleVersion,
            ])
            ->assertSessionHasErrors('manager_user_public_id');

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/teams/'.$team->public_id.'/structure?preview_manager='.$firstManager->public_id.'&preview_reports%5B%5D='.$report->public_id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Structure')
                ->where('selectedManagerPublicId', $firstManager->public_id)
                ->where('manager.userPublicId', $firstManager->public_id)
                ->where('teamMembers', fn (Collection $members): bool => $members->contains(
                    fn (mixed $member): bool => is_array($member)
                        && ($member['value'] ?? null) === $firstManager->public_id
                        && ($member['manager'] ?? null) === true,
                ) && $members->contains(
                    fn (mixed $member): bool => is_array($member)
                        && ($member['value'] ?? null) === $report->public_id
                        && ($member['manager'] ?? null) === false,
                ))
                ->has('relationships', 1)
                ->has('tree', 1)
                ->where('previewReportPublicIds', [$report->public_id])
                ->has('assignmentPreviews', 1)
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
            ->patch('/admin/teams/'.$team->public_id.'/structure/head-manager', [
                'team_public_id' => $team->public_id,
                'user_public_id' => $firstManager->public_id,
                'head_manager' => true,
                'reason' => 'Regional lead.',
            ])
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id, 'preview_manager' => $firstManager->public_id]));

        $headScope = $hierarchy->scopeFor((string) $team->public_id, (string) $firstManager->public_id);
        $normalScope = $hierarchy->scopeFor((string) $team->public_id, (string) $secondManager->public_id);

        self::assertTrue($headScope->headManager);
        self::assertContains((string) $teamLead->public_id, $headScope->visibleUserPublicIds);
        self::assertContains((string) $report->public_id, $headScope->visibleUserPublicIds);
        self::assertFalse($normalScope->headManager);
        self::assertSame([(string) $teamLead->public_id], $normalScope->visibleUserPublicIds);

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/teams/'.$team->public_id.'/structure/head-manager', [
                'team_public_id' => $team->public_id,
                'user_public_id' => $firstManager->public_id,
                'head_manager' => false,
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
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id, 'preview_manager' => $secondManager->public_id]));

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
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id, 'preview_manager' => $teamLead->public_id]));

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

    public function test_reparent_is_atomic_effective_dated_concurrency_safe_guarded_and_audited(): void
    {
        $actor = User::factory()->create();
        $oldManager = User::factory()->create(['name' => 'Old Manager']);
        $newManager = User::factory()->create(['name' => 'New Manager']);
        $report = User::factory()->create(['name' => 'Moved Report']);
        $team = Team::query()->create(['name' => 'Move Team']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);
        foreach ([$oldManager, $newManager, $report] as $member) {
            $this->assignMembership($member, $team);
        }
        $session = $this->adminSession($team);
        $this->createRelationship($actor, $session, $team, $oldManager, $report);
        $hierarchy = $this->app->make(ManagerHierarchy::class);
        $version = $hierarchy->version((string) $team->public_id);
        $oldRelationshipPublicId = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $team->id)->where('manager_user_id', $oldManager->id)->value('public_id');
        self::assertIsString($oldRelationshipPublicId);

        $this->actingAs($actor)->withSession($session)
            ->patch('/admin/teams/'.$team->public_id.'/structure/relationships/'.$oldRelationshipPublicId.'/reparent', [
                'new_manager_user_public_id' => $newManager->public_id,
                'effective_at' => now()->toDateString(),
                'reason' => 'Move the complete reporting subtree.',
                'structure_version' => $version,
            ])->assertRedirect(route('admin.teams.structure.show', [
                'team' => $team->public_id,
                'preview_manager' => $newManager->public_id,
            ]));

        self::assertDatabaseMissing(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, [
            'public_id' => $oldRelationshipPublicId,
            'valid_to' => null,
        ]);
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, [
            'team_id' => $team->id,
            'manager_user_id' => $newManager->id,
            'report_user_id' => $report->id,
            'valid_to' => null,
        ]);
        self::assertSame(2, DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)->where('report_user_id', $report->id)->count());
        self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, [
            'action' => 'team.manager_relationship.reparented',
            'result' => 'succeeded',
            'team_public_id' => $team->public_id,
        ]);

        $activeRelationshipPublicId = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $team->id)->where('manager_user_id', $newManager->id)->whereNull('valid_to')->value('public_id');
        self::assertIsString($activeRelationshipPublicId);
        $this->actingAs($actor)->withSession($session)
            ->patch('/admin/teams/'.$team->public_id.'/structure/relationships/'.$activeRelationshipPublicId.'/reparent', [
                'new_manager_user_public_id' => $oldManager->public_id,
                'effective_at' => now()->toDateString(),
                'reason' => 'Stale move.',
                'structure_version' => $version,
            ])->assertSessionHasErrors('new_manager_user_public_id');
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, ['public_id' => $activeRelationshipPublicId, 'valid_to' => null]);

        foreach ([$report->public_id, User::factory()->create()->public_id] as $invalidManagerPublicId) {
            try {
                $hierarchy->reparent(
                    (string) $actor->public_id,
                    (string) $team->public_id,
                    $activeRelationshipPublicId,
                    (string) $invalidManagerPublicId,
                    now()->toDateString(),
                    'Invalid invariant move.',
                    $hierarchy->version((string) $team->public_id),
                );
                self::fail('The invalid reparent should be rejected.');
            } catch (ManagerHierarchyViolation) {
                self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, ['public_id' => $activeRelationshipPublicId, 'valid_to' => null]);
            }
        }

        $hierarchy->assign(
            (string) $actor->public_id,
            (string) $team->public_id,
            (string) $report->public_id,
            (string) $oldManager->public_id,
            now()->toDateString(),
            'Cycle fixture relationship.',
        );
        $cycleRelationshipPublicId = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $team->id)->where('manager_user_id', $report->id)->where('report_user_id', $oldManager->id)->value('public_id');
        self::assertIsString($cycleRelationshipPublicId);
        try {
            $hierarchy->reparent(
                (string) $actor->public_id,
                (string) $team->public_id,
                $activeRelationshipPublicId,
                (string) $oldManager->public_id,
                now()->toDateString(),
                'Cycle move.',
                $hierarchy->version((string) $team->public_id),
            );
            self::fail('The cyclic reparent should be rejected.');
        } catch (ManagerHierarchyViolation $exception) {
            self::assertStringContainsString('cycle', $exception->getMessage());
        }
        $hierarchy->end((string) $actor->public_id, $cycleRelationshipPublicId, now()->toDateString(), 'Remove cycle fixture.');

        $this->app->bind(TeamStructureMutationGuard::class, static fn () => new class implements TeamStructureMutationGuard
        {
            public function assertReparentAllowed(string $teamPublicId, string $reportUserPublicId, string $currentManagerUserPublicId, string $newManagerUserPublicId): void
            {
                throw ManagerHierarchyViolation::activeProcess('An active work process blocks this move.');
            }
        });
        $guardedHierarchy = $this->app->make(ManagerHierarchy::class);
        $guardedVersion = $guardedHierarchy->version((string) $team->public_id);

        try {
            $guardedHierarchy->reparent(
                (string) $actor->public_id,
                (string) $team->public_id,
                $activeRelationshipPublicId,
                (string) $oldManager->public_id,
                now()->toDateString(),
                'Blocked move.',
                $guardedVersion,
            );
            self::fail('The active-process guard should reject the move.');
        } catch (ManagerHierarchyViolation $exception) {
            self::assertStringContainsString('active work process', $exception->getMessage());
        }
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, ['public_id' => $activeRelationshipPublicId, 'valid_to' => null]);
        self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, [
            'action' => 'team.manager_relationship.reparent_rejected',
            'result' => 'rejected',
            'target_public_id' => $activeRelationshipPublicId,
        ]);
    }

    public function test_reparent_rolls_back_both_relationship_changes_when_mandatory_audit_fails(): void
    {
        $actor = User::factory()->create();
        $oldManager = User::factory()->create();
        $newManager = User::factory()->create();
        $report = User::factory()->create();
        $team = Team::query()->create(['name' => 'Audit Rollback Team']);
        foreach ([$actor, $oldManager, $newManager, $report] as $member) {
            $this->assignMembership($member, $team);
        }
        $hierarchy = $this->app->make(ManagerHierarchy::class);
        $hierarchy->assign((string) $actor->public_id, (string) $team->public_id, (string) $oldManager->public_id, (string) $report->public_id, now()->toDateString(), 'Initial relationship.');
        $relationshipPublicId = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)->where('team_id', $team->id)->value('public_id');
        self::assertIsString($relationshipPublicId);

        $audit = new class implements AuditRecorder
        {
            public function record(AuditEvent $event): void
            {
                throw new RuntimeException('Audit unavailable.');
            }
        };
        $guard = $this->app->make(TeamStructureMutationGuard::class);
        $failingHierarchy = new DatabaseManagerHierarchy(
            $audit,
            $this->app->make(UserLookup::class),
            $guard,
        );

        try {
            $failingHierarchy->reparent(
                (string) $actor->public_id,
                (string) $team->public_id,
                $relationshipPublicId,
                (string) $newManager->public_id,
                now()->toDateString(),
                'Move with failed evidence.',
                $failingHierarchy->version((string) $team->public_id),
            );
            self::fail('Audit failure should abort the move.');
        } catch (RuntimeException $exception) {
            self::assertSame('Audit unavailable.', $exception->getMessage());
        }

        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, ['public_id' => $relationshipPublicId, 'valid_to' => null]);
        self::assertDatabaseMissing(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS, [
            'team_id' => $team->id,
            'manager_user_id' => $newManager->id,
            'report_user_id' => $report->id,
        ]);
    }

    /**
     * @param  array<string, int|string>  $session
     */
    private function createRelationship(User $actor, array $session, Team $team, User $manager, User $report): void
    {
        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/teams/'.$team->public_id.'/structure/relationships', [
                'team_public_id' => $team->public_id,
                'manager_user_public_id' => $manager->public_id,
                'report_user_public_id' => $report->public_id,
                'valid_from' => now()->toDateString(),
                'reason' => 'Approved reporting line.',
            ])
            ->assertRedirect(route('admin.teams.structure.show', ['team' => $team->public_id, 'preview_manager' => $manager->public_id]));
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
