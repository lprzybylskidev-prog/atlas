<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\ChatSearch;
use App\Modules\Optional\Chat\Application\Contracts\ChatSearchProjectionStore;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\MarkdownRenderer;
use App\Modules\Optional\Chat\Application\Contracts\MessageStore;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\MessageManager;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopePolicy;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\MessageIdempotencyConflict;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\MessageOperationDenied;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\StaleMessageEdit;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchClient;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchHit;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchQuery;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchResult;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Authorization\Contracts\AdministratorAccessLookup;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MessageBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_messages_support_safe_markdown_replies_mentions_and_idempotent_send(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $messages = $this->messages();
        $body = "**Bold** and *italic* with `code`.\n\n- item\n\n> quote\n\n[Safe](https://atlas.example) [Unsafe](javascript:alert(1)) <script>alert('x')</script> 😀 @everyone @online";

        $sent = $messages->send(
            (string) $author->public_id,
            'team',
            $conversation->publicId,
            $body,
            'send-001',
            mentionedUserPublicIds: [(string) $member->public_id],
        );
        $replayed = $messages->send(
            (string) $author->public_id,
            'team',
            $conversation->publicId,
            $body,
            'send-001',
            mentionedUserPublicIds: [(string) $member->public_id],
        );

        self::assertSame($sent->publicId, $replayed->publicId);
        self::assertSame(1, DB::table(ChatDatabaseTable::MESSAGES)->count());
        self::assertStringContainsString('<strong>Bold</strong>', (string) $sent->renderedHtml);
        self::assertStringContainsString('<em>italic</em>', (string) $sent->renderedHtml);
        self::assertStringNotContainsString('<script', (string) $sent->renderedHtml);
        self::assertStringNotContainsString('javascript:', (string) $sent->renderedHtml);
        self::assertTrue($sent->mentionsEveryone);
        self::assertTrue($sent->mentionsOnline);
        self::assertSame([(string) $member->public_id], $sent->mentionedUserPublicIds);

        $reply = $messages->send((string) $member->public_id, 'team', $conversation->publicId, "A multiline\nreply", 'send-002', $sent->publicId);
        self::assertSame($sent->publicId, $reply->replyToMessagePublicId);
        self::assertSame("A multiline\nreply", $reply->body);

        $this->expectException(MessageIdempotencyConflict::class);
        $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'Different content', 'send-001');
    }

    public function test_editing_preserves_history_and_rejects_stale_or_non_author_updates(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $messages = $this->messages();
        $sent = $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'First', 'edit-001');
        $edited = $messages->edit((string) $author->public_id, 'team', $conversation->publicId, $sent->publicId, 1, 'Second');

        self::assertSame(2, $edited->version);
        self::assertTrue($edited->edited);
        self::assertSame(['First', 'Second'], array_map(static fn ($revision): string => $revision->body, $messages->editHistory(
            (string) $member->public_id,
            'team',
            $conversation->publicId,
            $sent->publicId,
        )));

        try {
            $messages->edit((string) $author->public_id, 'team', $conversation->publicId, $sent->publicId, 1, 'Stale');
            self::fail('A stale message edit was accepted.');
        } catch (StaleMessageEdit) {
            self::assertSame('Second', DB::table(ChatDatabaseTable::MESSAGES)->where('public_id', $sent->publicId)->value('body'));
        }

        $this->expectException(MessageOperationDenied::class);
        $this->expectExceptionMessage('Only the message author');
        $messages->edit((string) $member->public_id, 'team', $conversation->publicId, $sent->publicId, 2, 'Unauthorized');
    }

    public function test_delete_for_me_is_a_private_tombstone_and_blocks_content_side_doors(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $messages = $this->messages();
        $sent = $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'Private body', 'delete-001');
        $projection = $this->app->make(ChatSearchProjectionStore::class);
        self::assertNotNull($projection->resolve('message-'.$sent->publicId, $member->id));
        $messages->bookmark((string) $member->public_id, 'team', $conversation->publicId, $sent->publicId);
        $tombstone = $messages->deleteForMe((string) $member->public_id, 'team', $conversation->publicId, $sent->publicId);

        self::assertTrue($tombstone->deletedForViewer);
        self::assertNull($tombstone->body);
        self::assertNull($tombstone->renderedHtml);
        self::assertFalse($tombstone->bookmarked);
        self::assertSame('Private body', $messages->messages((string) $author->public_id, 'team', $conversation->publicId)[0]->body);
        self::assertNull($projection->resolve('message-'.$sent->publicId, $member->id));
        self::assertNotNull($projection->resolve('message-'.$sent->publicId, $author->id));

        foreach ([
            fn () => $messages->editHistory((string) $member->public_id, 'team', $conversation->publicId, $sent->publicId),
            fn () => $messages->react((string) $member->public_id, 'team', $conversation->publicId, $sent->publicId, '👍'),
            fn () => $messages->forward((string) $member->public_id, 'team', $conversation->publicId, $sent->publicId, $conversation->publicId, 'hidden-forward'),
        ] as $operation) {
            try {
                $operation();
                self::fail('Delete-for-me content remained reachable through another action.');
            } catch (MessageOperationDenied $exception) {
                self::assertStringContainsString('not available', $exception->getMessage());
            }
        }
    }

    public function test_reactions_pins_bookmarks_and_forwarding_keep_their_required_scope(): void
    {
        [$author, $member, $destinationMember] = User::factory()->count(3)->create()->all();
        $conversations = $this->conversations();
        $source = $conversations->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $destination = $conversations->startDirect((string) $author->public_id, (string) $destinationMember->public_id, 'team');
        $messages = $this->messages();
        $sent = $messages->send((string) $author->public_id, 'team', $source->publicId, 'Forward this', 'state-001');

        $messages->react((string) $member->public_id, 'team', $source->publicId, $sent->publicId, '👍');
        $messages->react((string) $member->public_id, 'team', $source->publicId, $sent->publicId, '👍');
        $messages->pin((string) $member->public_id, 'team', $source->publicId, $sent->publicId);
        $messages->bookmark((string) $member->public_id, 'team', $source->publicId, $sent->publicId);

        $memberView = $messages->messages((string) $member->public_id, 'team', $source->publicId)[0];
        $authorView = $messages->messages((string) $author->public_id, 'team', $source->publicId)[0];
        self::assertCount(1, $memberView->reactions);
        self::assertTrue($memberView->pinned);
        self::assertTrue($memberView->bookmarked);
        self::assertFalse($authorView->bookmarked);

        $forwarded = $messages->forward((string) $author->public_id, 'team', $source->publicId, $sent->publicId, $destination->publicId, 'forward-001');
        self::assertTrue($forwarded->forwarded);
        self::assertSame('Forward this', $forwarded->body);
        self::assertNull($forwarded->replyToMessagePublicId);
        self::assertObjectNotHasProperty('forwardedFromMessagePublicId', $forwarded);
        self::assertObjectNotHasProperty('sourceConversationPublicId', $forwarded);
    }

    public function test_drafts_are_backend_owned_private_and_cleared_after_send(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $messages = $this->messages();
        $reply = $messages->send((string) $member->public_id, 'team', $conversation->publicId, 'Reply target', 'draft-target');
        $first = $messages->saveDraft((string) $author->public_id, 'team', $conversation->publicId, 'First draft', $reply->publicId);
        $second = $messages->saveDraft((string) $author->public_id, 'team', $conversation->publicId, "Updated\ndraft", $reply->publicId);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame($first->publicId, $second->publicId);
        self::assertSame(2, $second->version);
        self::assertSame("Updated\ndraft", $messages->draft((string) $author->public_id, 'team', $conversation->publicId)?->body);
        self::assertNull($messages->draft((string) $member->public_id, 'team', $conversation->publicId));

        $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'Sent', 'draft-send');
        self::assertNull($messages->draft((string) $author->public_id, 'team', $conversation->publicId));
        self::assertSame(0, DB::table(ChatDatabaseTable::MESSAGE_DRAFTS)->count());
    }

    public function test_cross_conversation_references_non_members_and_invalid_mentions_are_denied(): void
    {
        [$first, $second, $outsider] = User::factory()->count(3)->create()->all();
        $conversations = $this->conversations();
        $firstConversation = $conversations->startDirect((string) $first->public_id, (string) $second->public_id, 'team');
        $otherConversation = $conversations->startDirect((string) $first->public_id, (string) $outsider->public_id, 'team');
        $messages = $this->messages();
        $otherMessage = $messages->send((string) $first->public_id, 'team', $otherConversation->publicId, 'Other', 'negative-001');

        foreach ([
            fn () => $messages->messages((string) $outsider->public_id, 'team', $firstConversation->publicId),
            fn () => $messages->send((string) $first->public_id, 'team', $firstConversation->publicId, 'Bad reply', 'negative-002', $otherMessage->publicId),
            fn () => $messages->send((string) $first->public_id, 'team', $firstConversation->publicId, 'Bad mention', 'negative-003', mentionedUserPublicIds: [(string) $outsider->public_id]),
        ] as $operation) {
            try {
                $operation();
                self::fail('An unauthorized Chat message operation was accepted.');
            } catch (MessageOperationDenied $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_inactive_participants_cannot_be_new_mention_targets(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $member->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

        $this->expectException(MessageOperationDenied::class);
        $this->expectExceptionMessage('Only active conversation participants');
        $this->messages()->send(
            (string) $author->public_id,
            'team',
            $conversation->publicId,
            'Mention inactive member',
            'inactive-mention',
            mentionedUserPublicIds: [(string) $member->public_id],
        );
    }

    public function test_search_reauthorizes_stale_hits_after_membership_removal_and_never_grants_an_admin_bypass(): void
    {
        [$owner, $member, $administrator] = User::factory()->count(3)->create()->all();
        $this->app->make(InstallStarterRoles::class)->handle();
        $adminTeam = Team::query()->create(['name' => 'search-admin', 'display_name' => 'Search admin', 'is_active' => true]);
        $administratorRole = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();
        DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $administratorRole->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $administrator->id,
            'team_id' => $adminTeam->id,
        ]);
        self::assertTrue($this->app->make(AdministratorAccessLookup::class)->hasAdministratorLevelAccess((string) $administrator->public_id));
        $conversations = $this->conversations();
        $conversation = $conversations->createGroup(
            (string) $owner->public_id,
            'team',
            'Private project',
            [(string) $member->public_id],
        );
        $message = $this->messages()->send((string) $owner->public_id, 'team', $conversation->publicId, 'Confidential roadmap', 'search-auth-001');
        $search = new ChatSearch(
            new StaleChatSearchClient('message-'.$message->publicId),
            $this->app->make(ChatSearchProjectionStore::class),
            $conversations,
            new ChatModuleAccess($this->gate()),
            $this->app->make(UserLookup::class),
        );

        self::assertCount(1, $search->query((string) $member->public_id, 'team', 'roadmap')['items']);
        self::assertSame([], $search->query((string) $administrator->public_id, 'team', 'roadmap')['items']);

        $conversations->removeGroupMember((string) $owner->public_id, 'team', $conversation->publicId, (string) $member->public_id);
        self::assertSame([], $search->query((string) $member->public_id, 'team', 'roadmap')['items']);
    }

    public function test_search_maps_author_conversation_business_date_and_type_filters_to_the_projection_query(): void
    {
        $actor = User::factory()->create();
        $client = new RecordingChatSearchClient;
        $search = new ChatSearch(
            $client,
            $this->app->make(ChatSearchProjectionStore::class),
            $this->conversations(),
            new ChatModuleAccess($this->gate()),
            $this->app->make(UserLookup::class),
        );

        $search->query((string) $actor->public_id, 'team', 'decision', [
            'author' => 'Atlas User',
            'conversation' => '01K6A1B2C3D4E5F6G7H8J9K0MN',
            'date_from' => '2026-10-02',
            'date_to' => '2026-10-02',
            'type' => 'message',
        ]);

        self::assertNotNull($client->query);
        self::assertSame([
            'author_name' => 'Atlas User',
            'conversation_public_id' => '01K6A1B2C3D4E5F6G7H8J9K0MN',
            'occurred_at__gte' => '2026-10-01T22:00:00+00:00',
            'occurred_at__lte' => '2026-10-02T21:59:59+00:00',
            'result_type' => 'message',
        ], $client->query->filters);
    }

    public function test_revision_and_delete_for_me_evidence_cannot_be_rewritten(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $messages = $this->messages();
        $sent = $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'Immutable', 'immutable-001');
        $messages->deleteForMe((string) $member->public_id, 'team', $conversation->publicId, $sent->publicId);

        foreach ([ChatDatabaseTable::MESSAGE_EDIT_HISTORY, ChatDatabaseTable::MESSAGE_DELETIONS] as $table) {
            try {
                DB::transaction(static function () use ($table): void {
                    DB::table($table)->update(['id' => 999999]);
                });
                self::fail('Immutable Chat message evidence was updated.');
            } catch (QueryException $exception) {
                self::assertStringContainsString('immutable', $exception->getMessage());
            }
        }
    }

    private function conversations(): ConversationManager
    {
        return new ConversationManager(
            store: $this->app->make(ConversationStore::class),
            transaction: $this->app->make(ChatTransaction::class),
            access: new ChatModuleAccess($this->gate()),
            users: $this->app->make(UserLookup::class),
            teams: $this->app->make(TeamLookup::class),
            teamMemberships: $this->app->make(UserTeamMembershipManager::class),
            scopePolicy: new ConversationScopePolicy,
            audit: $this->app->make(AuditRecorder::class),
        );
    }

    private function messages(): MessageManager
    {
        return new MessageManager(
            messages: $this->app->make(MessageStore::class),
            conversations: $this->app->make(ConversationStore::class),
            transaction: $this->app->make(ChatTransaction::class),
            access: new ChatModuleAccess($this->gate()),
            users: $this->app->make(UserLookup::class),
            scopePolicy: new ConversationScopePolicy,
            markdown: $this->app->make(MarkdownRenderer::class),
        );
    }

    private function gate(): ModuleGate
    {
        return new class implements ModuleGate
        {
            public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
            {
                return ModuleAccessDecision::allow();
            }

            public function allows(ModuleAccessRequest $request): bool
            {
                return true;
            }
        };
    }
}

final readonly class StaleChatSearchClient implements SearchClient
{
    public function __construct(private string $documentId) {}

    public function search(SearchQuery $query): SearchResult
    {
        return new SearchResult($query->indexKey, [new SearchHit($this->documentId, 'chat', [])], 1);
    }
}

final class RecordingChatSearchClient implements SearchClient
{
    public ?SearchQuery $query = null;

    public function search(SearchQuery $query): SearchResult
    {
        $this->query = $query;

        return new SearchResult($query->indexKey, [], 0);
    }
}
