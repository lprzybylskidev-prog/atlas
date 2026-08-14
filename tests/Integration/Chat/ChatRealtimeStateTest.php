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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

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
