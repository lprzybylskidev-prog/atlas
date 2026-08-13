<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopePolicy;
use App\Modules\Optional\Chat\Domain\Conversations\Exceptions\ConversationOperationDenied;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessDenialReason;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class ConversationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_conversation_is_canonical_for_an_unordered_pair(): void
    {
        [$first, $second] = User::factory()->count(2)->create()->all();
        $manager = $this->manager();

        $created = $manager->startDirect((string) $first->public_id, (string) $second->public_id, 'active-team');
        $reversed = $manager->startDirect((string) $second->public_id, (string) $first->public_id, 'another-team');

        self::assertSame($created->publicId, $reversed->publicId);
        self::assertSame(1, DB::table(ChatDatabaseTable::DIRECT_CONVERSATION_PAIRS)->count());
        self::assertSame(2, DB::table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)->where('conversation_id', $created->id)->count());
    }

    public function test_database_arbiters_protect_canonical_conversations_and_single_active_ownership(): void
    {
        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'optional_chat')
            ->whereIn('indexname', [
                'optional_chat_direct_conversation_pairs_lower_user_id_higher_user_id_unique',
                'chat_conversations_team_unique',
                'chat_conversations_meeting_owner_unique',
                'chat_memberships_active_owner_unique',
                'chat_memberships_active_user_unique',
            ])
            ->pluck('indexdef', 'indexname')
            ->all();

        self::assertCount(5, $indexes);

        foreach ($indexes as $definition) {
            self::assertIsString($definition);
            self::assertStringContainsString('UNIQUE INDEX', $definition);
        }
    }

    public function test_inactive_users_cannot_join_new_direct_or_group_communication(): void
    {
        $active = User::factory()->create();
        $inactive = User::factory()->inactive()->create();
        $manager = $this->manager();

        foreach ([
            fn () => $manager->startDirect((string) $active->public_id, (string) $inactive->public_id, 'team'),
            fn () => $manager->createGroup((string) $active->public_id, 'team', 'Collections', [(string) $inactive->public_id]),
        ] as $operation) {
            try {
                $operation();
                self::fail('Inactive membership was accepted.');
            } catch (ConversationOperationDenied $exception) {
                self::assertSame('An inactive Atlas user cannot join new communication.', $exception->getMessage());
            }
        }
    }

    public function test_group_owner_must_transfer_before_leaving_and_history_remains_visible_to_new_members(): void
    {
        [$owner, $member] = User::factory()->count(2)->create()->all();
        $manager = $this->manager();
        $conversation = $manager->createGroup((string) $owner->public_id, 'team-a', 'Legal');
        $manager->addGroupMember((string) $owner->public_id, 'team-a', $conversation->publicId, (string) $member->public_id);

        $memberTimeline = $manager->timelineFor((string) $member->public_id, 'unrelated-team', $conversation->publicId);
        self::assertSame(['group.created', 'group.member_added'], array_map(static fn ($entry): string => $entry->type->value, $memberTimeline));

        $this->expectException(ConversationOperationDenied::class);
        $this->expectExceptionMessage('must transfer ownership');
        $manager->leaveGroup((string) $owner->public_id, 'team-a', $conversation->publicId);
    }

    public function test_group_transfer_leave_and_last_member_close_preserve_membership_and_timeline_history(): void
    {
        [$owner, $member] = User::factory()->count(2)->create()->all();
        $manager = $this->manager();
        $conversation = $manager->createGroup((string) $owner->public_id, 'team-a', 'Operations', [(string) $member->public_id]);

        $manager->transferGroupOwnership((string) $owner->public_id, 'team-a', $conversation->publicId, (string) $member->public_id);
        $manager->leaveGroup((string) $owner->public_id, 'team-a', $conversation->publicId);
        $manager->leaveGroup((string) $member->public_id, 'team-a', $conversation->publicId);

        $this->assertDatabaseHas(ChatDatabaseTable::CONVERSATIONS, ['public_id' => $conversation->publicId]);
        self::assertSame(2, DB::table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)->where('conversation_id', $conversation->id)->whereNotNull('ended_at')->count());
        self::assertNotNull(DB::table(ChatDatabaseTable::CONVERSATIONS)->where('id', $conversation->id)->value('closed_at'));
        self::assertSame(
            ['group.created', 'group.member_added', 'group.ownership_transferred', 'group.member_left', 'group.member_left', 'group.closed'],
            DB::table(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES)->where('conversation_id', $conversation->id)->orderBy('id')->pluck('type')->all(),
        );
    }

    public function test_non_owner_cannot_mutate_group_membership(): void
    {
        [$owner, $member, $target] = User::factory()->count(3)->create()->all();
        $manager = $this->manager();
        $conversation = $manager->createGroup((string) $owner->public_id, 'team-a', 'Enforcement', [(string) $member->public_id]);

        $this->expectException(ConversationOperationDenied::class);
        $this->expectExceptionMessage('Only the group owner');
        $manager->addGroupMember((string) $member->public_id, 'team-a', $conversation->publicId, (string) $target->public_id);
    }

    public function test_mandatory_group_audit_failure_rolls_back_conversation_and_memberships(): void
    {
        $owner = User::factory()->create();
        $audit = new class implements AuditRecorder
        {
            public function record(AuditEvent $event): void
            {
                throw new RuntimeException('Audit storage unavailable.');
            }
        };

        try {
            $this->manager(audit: $audit)->createGroup((string) $owner->public_id, 'team-a', 'Rollback group');
            self::fail('Audit failure did not abort group creation.');
        } catch (RuntimeException $exception) {
            self::assertSame('Audit storage unavailable.', $exception->getMessage());
        }

        self::assertSame(0, DB::table(ChatDatabaseTable::CONVERSATIONS)->where('type', 'group')->count());
        self::assertSame(0, DB::table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)->count());
        self::assertSame(0, DB::table(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES)->count());
    }

    public function test_structural_timeline_entries_cannot_be_updated(): void
    {
        $owner = User::factory()->create();
        $conversation = $this->manager()->createGroup((string) $owner->public_id, 'team-a', 'Immutable timeline');

        try {
            DB::transaction(static function () use ($conversation): void {
                DB::table(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES)
                    ->where('conversation_id', $conversation->id)
                    ->update(['type' => 'group.closed']);
            });
            self::fail('Timeline update was accepted.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('immutable', $exception->getMessage());
        }

        $this->assertDatabaseHas(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES, [
            'conversation_id' => $conversation->id,
            'type' => 'group.created',
        ]);
    }

    public function test_team_membership_mutations_create_and_synchronize_one_system_conversation(): void
    {
        $actor = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::query()->create(['name' => 'recoveries', 'display_name' => 'Recoveries']);
        $memberships = $this->app->make(UserTeamMembershipManager::class);

        $memberships->addAccess((string) $actor->public_id, (string) $member->public_id, (string) $team->public_id);
        $conversation = DB::table(ChatDatabaseTable::CONVERSATIONS)->where('team_public_id', (string) $team->public_id)->first();

        self::assertNotNull($conversation);
        self::assertTrue((bool) $conversation->system_owned);
        self::assertSame(1, DB::table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)->where('conversation_id', $conversation->id)->whereNull('ended_at')->count());

        $memberships->removeAccess((string) $actor->public_id, (string) $member->public_id, (string) $team->public_id, 'Moved to another team.');

        self::assertSame(0, DB::table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)->where('conversation_id', $conversation->id)->whereNull('ended_at')->count());
        self::assertSame(1, DB::table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)->where('conversation_id', $conversation->id)->whereNotNull('ended_at')->count());
        self::assertSame(2, DB::table(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES)->where('conversation_id', $conversation->id)->where('type', 'team.membership_synchronized')->count());
    }

    public function test_team_conversation_access_requires_current_team_but_group_access_does_not(): void
    {
        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'field', 'display_name' => 'Field']);
        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'structural_role' => 'employee',
            'valid_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $manager = $this->manager();
        $teamConversation = $manager->synchronizeTeamConversationRecord((string) $team->public_id);
        $group = $manager->createGroup((string) $user->public_id, (string) $team->public_id, 'Global group');

        self::assertTrue($manager->canAccess((string) $user->public_id, (string) $team->public_id, $teamConversation->publicId));
        self::assertFalse($manager->canAccess((string) $user->public_id, 'different-team', $teamConversation->publicId));
        self::assertTrue($manager->canAccess((string) $user->public_id, 'different-team', $group->publicId));
    }

    public function test_recurring_meetings_share_one_chat_and_decline_preserves_access_until_removal(): void
    {
        [$organizer, $invitee] = User::factory()->count(2)->create()->all();
        $manager = $this->manager();
        $first = $manager->ensureMeetingConversation('meeting-01', 'series-01', (string) $organizer->public_id);
        $second = $manager->ensureMeetingConversation('meeting-02', 'series-01', (string) $organizer->public_id);

        self::assertSame($first->publicId, $second->publicId);
        self::assertTrue($first->systemOwned);

        $manager->inviteMeetingParticipant($first->publicId, (string) $organizer->public_id, (string) $invitee->public_id);
        $manager->changeMeetingResponse($first->publicId, (string) $invitee->public_id, MeetingResponse::Declined);

        self::assertTrue($manager->canAccess((string) $invitee->public_id, 'any-team', $first->publicId));
        $this->assertDatabaseHas(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS, [
            'conversation_id' => $first->id,
            'user_id' => $invitee->id,
            'meeting_response' => 'declined',
            'ended_at' => null,
        ]);

        $manager->removeMeetingParticipant($first->publicId, (string) $organizer->public_id, (string) $invitee->public_id);

        self::assertFalse($manager->canAccess((string) $invitee->public_id, 'any-team', $first->publicId));
        self::assertSame(1, DB::table(ChatDatabaseTable::CONVERSATIONS)->where('meeting_owner_key', 'series-01')->count());
    }

    public function test_module_permission_denial_blocks_creation_and_content_access(): void
    {
        [$first, $second] = User::factory()->count(2)->create()->all();
        $allowed = $this->manager();
        $conversation = $allowed->startDirect((string) $first->public_id, (string) $second->public_id, 'team');
        $denied = $this->manager(false);

        self::assertFalse($denied->canAccess((string) $first->public_id, 'team', $conversation->publicId));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('globally_inactive');
        $denied->startDirect((string) $first->public_id, (string) $second->public_id, 'team');
    }

    private function manager(bool $allowed = true, ?AuditRecorder $audit = null): ConversationManager
    {
        $gate = new class($allowed) implements ModuleGate
        {
            public function __construct(private readonly bool $allowed) {}

            public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
            {
                return $this->allowed
                    ? ModuleAccessDecision::allow()
                    : ModuleAccessDecision::deny(ModuleAccessDenialReason::GloballyInactive);
            }

            public function allows(ModuleAccessRequest $request): bool
            {
                return $this->inspect($request)->allowed;
            }
        };

        return new ConversationManager(
            store: $this->app->make(ConversationStore::class),
            transaction: $this->app->make(ChatTransaction::class),
            access: new ChatModuleAccess($gate),
            users: $this->app->make(UserLookup::class),
            teams: $this->app->make(TeamLookup::class),
            teamMemberships: $this->app->make(UserTeamMembershipManager::class),
            scopePolicy: new ConversationScopePolicy,
            audit: $audit ?? $this->app->make(AuditRecorder::class),
        );
    }
}
