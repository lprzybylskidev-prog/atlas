<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Core\Audit\Infrastructure\Persistence\TableNames\AuditDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\Chat\Application\CallManager;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\Contracts\CallStore;
use App\Modules\Optional\Chat\Application\Contracts\ChatRealtimePublisher;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\DTOs\CallPreferences;
use App\Modules\Optional\Chat\Application\Exceptions\RtcAccessDenied;
use App\Modules\Optional\Chat\Domain\Calls\Exceptions\CallOperationDenied;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopePolicy;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Modules\Optional\Chat\Infrastructure\Rtc\DatabaseCallSessionAccessAuthorizer;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CallLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private ?RecordingCallRealtimePublisher $realtime = null;

    private ?RecordingCallNotificationPublisher $notificationPublisher = null;

    public function test_direct_call_is_idempotent_has_one_active_call_and_supports_join_and_rejoin(): void
    {
        [$caller, $recipient] = User::factory()->count(2)->create()->all();
        [$conversations, $calls] = $this->managers();
        $conversation = $conversations->startDirect((string) $caller->public_id, (string) $recipient->public_id, 'team');

        $started = $calls->start((string) $caller->public_id, 'team', $conversation->publicId, true, 'request-1');
        $replayed = $calls->start((string) $caller->public_id, 'team', $conversation->publicId, true, 'request-1');
        $parallelAttempt = $calls->start((string) $recipient->public_id, 'team', $conversation->publicId, false, 'request-2');

        self::assertTrue($started->created);
        self::assertFalse($replayed->created);
        self::assertFalse($parallelAttempt->created);
        self::assertSame($started->call->publicId, $replayed->call->publicId);
        self::assertSame($started->call->publicId, $parallelAttempt->call->publicId);
        self::assertTrue($parallelAttempt->call->incoming);
        self::assertSame(1, DB::table(ChatDatabaseTable::CALLS)->count());

        try {
            $calls->start((string) $caller->public_id, 'team', $conversation->publicId, false, 'request-1');
            self::fail('An idempotency key was accepted for a different initial media mode.');
        } catch (CallOperationDenied $exception) {
            self::assertSame('idempotency_conflict', $exception->reason);
        }

        $joined = $calls->join((string) $recipient->public_id, 'team', $started->call->publicId, false, true);
        self::assertSame('active', $joined->status->value);
        self::assertSame('joined', $joined->currentUserState->value);
        self::assertFalse($joined->initialCameraEnabled === false, 'The persisted initial mode must remain the caller-selected video mode.');

        $left = $calls->leave((string) $recipient->public_id, 'team', $started->call->publicId);
        self::assertTrue($left->canRejoin);
        self::assertSame('left', $left->currentUserState->value);

        $rejoined = $calls->join((string) $recipient->public_id, 'team', $started->call->publicId, false, false);
        self::assertSame('joined', $rejoined->currentUserState->value);
    }

    public function test_group_call_rings_members_and_enforces_busy_and_one_screen_share(): void
    {
        [$owner, $first, $second, $otherCaller] = User::factory()->count(4)->create()->all();
        [$conversations, $calls] = $this->managers();
        $group = $conversations->createGroup(
            (string) $owner->public_id,
            'team',
            'Operations',
            [(string) $first->public_id, (string) $second->public_id],
        );
        $started = $calls->start((string) $owner->public_id, 'team', $group->publicId, false, 'group-request');

        self::assertSame('ringing', $started->call->status->value);
        self::assertNotNull($this->realtime);
        self::assertCount(2, array_filter($this->realtime->events, static fn (array $event): bool => $event['event'] === 'chat.call.incoming'));

        $calls->join((string) $first->public_id, 'team', $started->call->publicId, false, true);
        $calls->join((string) $second->public_id, 'team', $started->call->publicId, true, true);
        $calls->setScreenShare((string) $owner->public_id, 'team', $started->call->publicId, true);

        try {
            $calls->setScreenShare((string) $first->public_id, 'team', $started->call->publicId, true);
            self::fail('A second simultaneous screen share was accepted.');
        } catch (CallOperationDenied $exception) {
            self::assertSame('screen_share_busy', $exception->reason);
        }

        $busyConversation = $conversations->startDirect((string) $otherCaller->public_id, (string) $first->public_id, 'team');
        $busy = $calls->start((string) $otherCaller->public_id, 'team', $busyConversation->publicId, false, 'busy-request');
        self::assertSame('busy', $busy->call->status->value);
        self::assertSame('left', $busy->call->currentUserState->value);
    }

    public function test_team_call_is_active_quiet_join_style_and_allows_eligible_member_join(): void
    {
        [$starter, $member] = User::factory()->count(2)->create()->all();
        $team = Team::query()->create(['name' => 'legal', 'display_name' => 'Legal']);
        $this->assign($team, $starter);
        $this->assign($team, $member);
        [$conversations, $calls] = $this->managers();
        $conversation = $conversations->synchronizeTeamConversationRecord((string) $team->public_id);

        $started = $calls->start((string) $starter->public_id, (string) $team->public_id, $conversation->publicId, false, 'team-request');

        self::assertSame('active', $started->call->status->value);
        self::assertTrue($started->call->teamJoinStyle);
        self::assertNotNull($this->realtime);
        self::assertContains('chat.call.available', array_column($this->realtime->events, 'event'));

        $current = $calls->current((string) $member->public_id, (string) $team->public_id);
        self::assertNotNull($current);
        self::assertSame('notified', $current->currentUserState->value);
        self::assertFalse($current->incoming);

        $joined = $calls->join((string) $member->public_id, (string) $team->public_id, $started->call->publicId, false, true);
        self::assertSame('joined', $joined->currentUserState->value);
    }

    public function test_missed_call_creates_timeline_notification_history_and_no_email(): void
    {
        [$caller, $recipient] = User::factory()->count(2)->create()->all();
        [$conversations, $calls] = $this->managers();
        $conversation = $conversations->startDirect((string) $caller->public_id, (string) $recipient->public_id, 'team');
        $started = $calls->start((string) $caller->public_id, 'team', $conversation->publicId, false, 'missed-request');

        $ended = $calls->leave((string) $caller->public_id, 'team', $started->call->publicId);

        self::assertSame('missed', $ended->status->value);
        $this->assertDatabaseHas(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES, [
            'conversation_id' => $conversation->id,
            'type' => 'call.missed',
        ]);
        self::assertNotNull($this->notificationPublisher);
        self::assertCount(1, $this->notificationPublisher->notifications);
        self::assertSame('chat.call.missed', $this->notificationPublisher->notifications[0]->type);
        self::assertFalse($this->notificationPublisher->notifications[0]->emailRequested);
        self::assertSame('missed', $calls->history((string) $recipient->public_id, 'team')[0]->state);
        self::assertSame([
            'chat.call.ended',
            'chat.call.left',
            'chat.call.started',
        ], DB::table(AuditDatabaseTable::AUDIT_EVENTS)->where('aggregate_public_id', $started->call->publicId)->orderBy('action')->pluck('action')->all());
    }

    public function test_preferences_rtc_authorization_and_database_arbiters_are_enforced(): void
    {
        [$first, $second, $third] = User::factory()->count(3)->create()->all();
        [$conversations, $calls] = $this->managers();
        $conversation = $conversations->startDirect((string) $first->public_id, (string) $second->public_id, 'team');
        $started = $calls->start((string) $first->public_id, 'team', $conversation->publicId, false, 'rtc-request');
        $calls->savePreferences((string) $first->public_id, 'team', new CallPreferences('camera-1', 'microphone-1', 'speaker-1', true));

        $preferences = $calls->preferences((string) $first->public_id, 'team');
        self::assertSame('camera-1', $preferences->cameraDeviceId);
        self::assertSame('microphone-1', $preferences->microphoneDeviceId);
        self::assertSame('speaker-1', $preferences->speakerDeviceId);
        self::assertTrue($preferences->outgoingCameraEnabled);

        $authorizer = new DatabaseCallSessionAccessAuthorizer(
            $this->app->make(CallStore::class),
            $this->app->make(ConversationStore::class),
            $conversations,
            $this->app->make(UserLookup::class),
        );
        $admission = $authorizer->authorize($started->call->publicId, (string) $first->public_id, 'team');
        self::assertSame('ad_hoc_call', $admission->mode->value);

        $this->expectException(RtcAccessDenied::class);
        $authorizer->authorize($started->call->publicId, (string) $third->public_id, 'team');
    }

    public function test_database_unique_indexes_protect_concurrent_call_invariants(): void
    {
        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'optional_chat')
            ->whereIn('indexname', [
                'chat_calls_active_conversation_unique',
                'chat_call_participants_active_user_unique',
                'chat_call_participants_screen_share_unique',
                'chat_calls_starter_request_unique',
            ])
            ->pluck('indexdef', 'indexname')
            ->all();

        self::assertCount(4, $indexes);
        foreach ($indexes as $definition) {
            self::assertIsString($definition);
            self::assertStringContainsString('UNIQUE INDEX', $definition);
        }

        [$first, $second, $third] = User::factory()->count(3)->create()->all();
        [$conversations, $calls] = $this->managers();
        $one = $conversations->startDirect((string) $first->public_id, (string) $second->public_id, 'team');
        $two = $conversations->startDirect((string) $first->public_id, (string) $third->public_id, 'team');
        $calls->start((string) $first->public_id, 'team', $one->publicId, false, 'unique-one');

        try {
            $calls->start((string) $first->public_id, 'team', $two->publicId, false, 'unique-two');
            self::fail('A second active RTC session was accepted.');
        } catch (CallOperationDenied $exception) {
            self::assertSame('busy', $exception->reason);
        }
    }

    /** @return array{ConversationManager, CallManager} */
    private function managers(): array
    {
        $gate = new class implements ModuleGate
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
        $access = new ChatModuleAccess($gate);
        $conversations = new ConversationManager(
            store: $this->app->make(ConversationStore::class),
            transaction: $this->app->make(ChatTransaction::class),
            access: $access,
            users: $this->app->make(UserLookup::class),
            teams: $this->app->make(TeamLookup::class),
            teamMemberships: $this->app->make(UserTeamMembershipManager::class),
            scopePolicy: new ConversationScopePolicy,
            audit: $this->app->make(AuditRecorder::class),
        );
        $this->realtime = new RecordingCallRealtimePublisher;
        $this->notificationPublisher = new RecordingCallNotificationPublisher;
        $calls = new CallManager(
            calls: $this->app->make(CallStore::class),
            conversations: $this->app->make(ConversationStore::class),
            conversationAccess: $conversations,
            transaction: $this->app->make(ChatTransaction::class),
            moduleAccess: $access,
            users: $this->app->make(UserLookup::class),
            realtime: $this->realtime,
            notifications: $this->notificationPublisher,
            audit: $this->app->make(AuditRecorder::class),
        );

        return [$conversations, $calls];
    }

    private function assign(Team $team, User $user): void
    {
        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'structural_role' => 'employee',
            'valid_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

final class RecordingCallRealtimePublisher implements ChatRealtimePublisher
{
    /** @var list<array{user: string, event: string}> */
    public array $events = [];

    public function conversation(string $conversationPublicId, string $event, array $payload): void {}

    public function user(string $userPublicId, string $event, array $payload): void
    {
        $this->events[] = ['user' => $userPublicId, 'event' => $event];
    }
}

final class RecordingCallNotificationPublisher implements NotificationPublisher
{
    /** @var list<CreateNotification> */
    public array $notifications = [];

    public function publish(CreateNotification $notification): string
    {
        $this->notifications[] = $notification;

        return 'notification-'.count($this->notifications);
    }
}
