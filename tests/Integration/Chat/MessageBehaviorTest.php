<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
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
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $messages->bookmark((string) $member->public_id, 'team', $conversation->publicId, $sent->publicId);
        $tombstone = $messages->deleteForMe((string) $member->public_id, 'team', $conversation->publicId, $sent->publicId);

        self::assertTrue($tombstone->deletedForViewer);
        self::assertNull($tombstone->body);
        self::assertNull($tombstone->renderedHtml);
        self::assertFalse($tombstone->bookmarked);
        self::assertSame('Private body', $messages->messages((string) $author->public_id, 'team', $conversation->publicId)[0]->body);

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
