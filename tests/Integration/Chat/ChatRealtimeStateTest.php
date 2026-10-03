<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\Contracts\ChatRealtimePublisher;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\MarkdownRenderer;
use App\Modules\Optional\Chat\Application\Contracts\MessageStore;
use App\Modules\Optional\Chat\Application\Contracts\RealtimeStore;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\MessageManager;
use App\Modules\Optional\Chat\Application\RealtimeManager;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopePolicy;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\MessageOperationDenied;
use App\Modules\Optional\Chat\Domain\Realtime\ManualStatus;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use UnexpectedValueException;

final class ChatRealtimeStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_delivery_read_and_mark_unread_use_monotonic_membership_cursors(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $messages = $this->messages();
        $first = $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'First', 'realtime-first');
        $second = $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'Second', 'realtime-second');
        $realtime = $this->realtime($messages);

        $initial = $realtime->reconcile((string) $member->public_id, 'team', $conversation->publicId, null);
        self::assertCount(2, $initial['messages']);
        self::assertSame(2, $initial['state']->unreadCount);
        self::assertSame($first->publicId, $initial['state']->firstUnreadMessagePublicId);

        $realtime->markDelivered((string) $member->public_id, 'team', $conversation->publicId, $second->publicId);
        $realtime->markDelivered((string) $member->public_id, 'team', $conversation->publicId, $first->publicId);
        $realtime->markRead((string) $member->public_id, 'team', $conversation->publicId, $second->publicId);
        $read = $realtime->reconcile((string) $member->public_id, 'team', $conversation->publicId, $first->publicId);
        self::assertCount(1, $read['messages']);
        self::assertSame(0, $read['state']->unreadCount);
        self::assertSame($second->publicId, $read['state']->lastDeliveredMessagePublicId);
        self::assertSame($second->publicId, $read['state']->lastReadMessagePublicId);

        $realtime->markUnread((string) $member->public_id, 'team', $conversation->publicId, $second->publicId);
        $unread = $realtime->reconcile((string) $member->public_id, 'team', $conversation->publicId, $second->publicId);
        self::assertSame(1, $unread['state']->unreadCount);
        self::assertSame($second->publicId, $unread['state']->firstUnreadMessagePublicId);
        self::assertSame(1, $unread['totalUnread']);
    }

    public function test_presence_manual_status_and_heartbeat_are_bounded_and_dnd_is_informational(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $publisher = new RecordingChatRealtimePublisher;
        $realtime = $this->realtime($this->messages(), $publisher);
        $first = $realtime->heartbeat((string) $author->public_id, 'team');
        $storedAt = DB::table(ChatDatabaseTable::USER_PRESENCE)->where('user_id', $author->id)->value('updated_at');
        $second = $realtime->heartbeat((string) $author->public_id, 'team');

        self::assertTrue($first->online);
        self::assertTrue($second->online);
        self::assertSame($storedAt, DB::table(ChatDatabaseTable::USER_PRESENCE)->where('user_id', $author->id)->value('updated_at'));

        $status = $realtime->updateStatus((string) $author->public_id, 'team', ManualStatus::DoNotDisturb, 'Deep work', '🔕');
        self::assertSame(ManualStatus::DoNotDisturb, $status->manualStatus);
        self::assertSame('Deep work', $status->customText);
        self::assertSame('🔕', $status->customEmoji);
        self::assertTrue($publisher->hasConversationEvent($conversation->publicId, 'chat.presence.updated'));
        self::assertCount(2, $realtime->reconcile((string) $member->public_id, 'team', $conversation->publicId, null)['presence']);

        $message = $this->messages()->send((string) $member->public_id, 'team', $conversation->publicId, 'DND still receives persisted messages', 'dnd-message');
        self::assertSame(1, $realtime->reconcile((string) $author->public_id, 'team', $conversation->publicId, null)['state']->unreadCount);
        self::assertSame('DND still receives persisted messages', $message->body);
    }

    public function test_message_history_uses_bounded_cursor_pages_with_constant_query_hydration(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $store = $this->app->make(MessageStore::class);
        $created = [];

        foreach (range(1, 121) as $sequence) {
            $created[] = $store->create(
                conversationId: $conversation->id,
                authorUserId: $author->id,
                body: 'Scale message '.$sequence,
                replyToMessageId: null,
                forwardedFromMessageId: null,
                clientMessageKey: 'scale-'.$sequence,
                requestHash: hash('sha256', 'scale-'.$sequence),
            );
        }

        $messages = $this->messages();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $small = $messages->messagePage((string) $member->public_id, 'team', $conversation->publicId, limit: 10);
        $smallQueryCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        $latest = $messages->messagePage((string) $member->public_id, 'team', $conversation->publicId);
        $largeQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertCount(10, $small->messages);
        self::assertCount(50, $latest->messages);
        self::assertTrue($latest->hasOlder);
        self::assertFalse($latest->hasNewer);
        self::assertLessThanOrEqual($smallQueryCount + 1, $largeQueryCount, 'Message query count must not grow with page size.');
        self::assertSame($created[71]->publicId, $latest->messages[0]->publicId);
        self::assertSame($created[120]->publicId, $latest->messages[49]->publicId);

        $middle = $messages->messagePage(
            (string) $member->public_id,
            'team',
            $conversation->publicId,
            beforeMessagePublicId: $latest->oldestMessagePublicId(),
        );
        $oldest = $messages->messagePage(
            (string) $member->public_id,
            'team',
            $conversation->publicId,
            beforeMessagePublicId: $middle->oldestMessagePublicId(),
        );
        self::assertCount(50, $middle->messages);
        self::assertTrue($middle->hasOlder);
        self::assertCount(21, $oldest->messages);
        self::assertFalse($oldest->hasOlder);
        self::assertSame($created[0]->publicId, $oldest->messages[0]->publicId);

        $reconnect = $messages->messagePage(
            (string) $member->public_id,
            'team',
            $conversation->publicId,
            afterMessagePublicId: $created[20]->publicId,
        );
        self::assertCount(50, $reconnect->messages);
        self::assertTrue($reconnect->hasNewer);
        self::assertSame($created[21]->publicId, $reconnect->messages[0]->publicId);
    }

    public function test_company_scale_fixture_supports_four_hundred_group_participants_without_product_limit_or_presence_n_plus_one(): void
    {
        $users = User::factory()->count(400)->sequence(
            fn (Sequence $sequence): array => [
                'name' => sprintf('Scale User %03d', $sequence->index + 1),
                'email' => sprintf('scale-user-%03d@example.test', $sequence->index + 1),
            ],
        )->create();
        $owner = $users->firstOrFail();
        $memberPublicIds = [];
        foreach ($users->skip(1) as $user) {
            $publicId = $user->getAttribute('public_id');
            if (! is_string($publicId)) {
                throw new UnexpectedValueException('Scale fixture user is missing a public identifier.');
            }

            $memberPublicIds[] = $publicId;
        }
        $conversation = $this->conversations()->createGroup(
            (string) $owner->public_id,
            'team',
            'Company-wide scale fixture',
            $memberPublicIds,
        );
        $viewer = $users->last();
        self::assertNotNull($viewer);
        $realtime = $this->realtime($this->messages());

        DB::enableQueryLog();
        DB::flushQueryLog();
        $snapshot = $realtime->reconcile((string) $viewer->public_id, 'team', $conversation->publicId, null);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertCount(400, $snapshot['presence']);
        self::assertLessThan(30, $queryCount, 'Presence reconciliation must use batch identity lookup at company scale.');
        self::assertSame(400, DB::table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)->where('conversation_id', $conversation->id)->count());
    }

    public function test_private_and_presence_channel_authorization_denies_guessed_identifiers_and_outsiders(): void
    {
        [$author, $member, $outsider] = User::factory()->count(3)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $realtime = $this->realtime($this->messages());

        self::assertTrue($realtime->authorizeUserChannel((string) $author->public_id, 'team', (string) $author->public_id));
        self::assertFalse($realtime->authorizeUserChannel((string) $author->public_id, 'team', (string) $member->public_id));
        self::assertSame((string) $author->public_id, $realtime->authorizeConversationChannel((string) $author->public_id, 'team', $conversation->publicId)['id']);

        foreach ([$conversation->publicId, '01JZZZZZZZZZZZZZZZZZZZZZZZ'] as $guessed) {
            try {
                $realtime->authorizeConversationChannel((string) $outsider->public_id, 'team', $guessed);
                self::fail('An outsider subscribed to a guessed Chat conversation channel.');
            } catch (MessageOperationDenied) {
            }
        }
    }

    private function realtime(MessageManager $messages, ?ChatRealtimePublisher $publisher = null): RealtimeManager
    {
        return new RealtimeManager(
            realtime: $this->app->make(RealtimeStore::class),
            conversations: $this->app->make(ConversationStore::class),
            messages: $this->app->make(MessageStore::class),
            messageManager: $messages,
            access: new ChatModuleAccess($this->gate()),
            users: $this->app->make(UserLookup::class),
            scopePolicy: new ConversationScopePolicy,
            publisher: $publisher ?? new class implements ChatRealtimePublisher
            {
                public function conversation(string $conversationPublicId, string $event, array $payload): void {}

                public function user(string $userPublicId, string $event, array $payload): void {}
            },
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

final class RecordingChatRealtimePublisher implements ChatRealtimePublisher
{
    /** @var list<array{conversation: string, event: string}> */
    private array $conversationEvents = [];

    public function conversation(string $conversationPublicId, string $event, array $payload): void
    {
        $this->conversationEvents[] = ['conversation' => $conversationPublicId, 'event' => $event];
    }

    public function user(string $userPublicId, string $event, array $payload): void {}

    public function hasConversationEvent(string $conversationPublicId, string $event): bool
    {
        foreach ($this->conversationEvents as $record) {
            if ($record['conversation'] === $conversationPublicId && $record['event'] === $event) {
                return true;
            }
        }

        return false;
    }
}
