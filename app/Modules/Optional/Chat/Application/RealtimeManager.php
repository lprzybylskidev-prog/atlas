<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Contracts\ChatRealtimePublisher;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\MessageStore;
use App\Modules\Optional\Chat\Application\Contracts\RealtimeStore;
use App\Modules\Optional\Chat\Application\DTOs\ConversationRealtimeState;
use App\Modules\Optional\Chat\Application\DTOs\ConversationRecord;
use App\Modules\Optional\Chat\Application\DTOs\MessageRecord;
use App\Modules\Optional\Chat\Application\DTOs\ParticipantCursor;
use App\Modules\Optional\Chat\Application\DTOs\PresenceSummary;
use App\Modules\Optional\Chat\Application\DTOs\VisibleMessage;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopeContext;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopePolicy;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\MessageOperationDenied;
use App\Modules\Optional\Chat\Domain\Realtime\ManualStatus;
use InvalidArgumentException;

final readonly class RealtimeManager
{
    public function __construct(
        private RealtimeStore $realtime,
        private ConversationStore $conversations,
        private MessageStore $messages,
        private MessageManager $messageManager,
        private ChatModuleAccess $access,
        private UserLookup $users,
        private ConversationScopePolicy $scopePolicy,
        private ChatRealtimePublisher $publisher,
    ) {}

    /**
     * @return array{
     *     messages: list<VisibleMessage>,
     *     state: ConversationRealtimeState,
     *     participantCursors: list<ParticipantCursor>,
     *     presence: list<PresenceSummary>,
     *     totalUnread: int
     * }
     */
    public function reconcile(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId, ?string $afterMessagePublicId): array
    {
        [$conversation, $userId] = $this->participant($userPublicId, $activeTeamPublicId, $conversationPublicId);
        $afterId = null;

        if ($afterMessagePublicId !== null) {
            $after = $this->messageInConversation($afterMessagePublicId, $conversation->id);
            $afterId = $after->id;
        }

        $visible = $this->messageManager->messages($userPublicId, $activeTeamPublicId, $conversationPublicId);
        if ($afterId !== null) {
            $ids = [];
            foreach ($this->messages->conversationMessages($conversation->id) as $record) {
                $ids[$record->publicId] = $record->id;
            }
            $visible = array_values(array_filter($visible, static fn ($message): bool => ($ids[$message->publicId] ?? 0) > $afterId));
        }

        return [
            'messages' => $visible,
            'state' => $this->realtime->state($conversation->id, $userId),
            'participantCursors' => $this->realtime->participantCursors($conversation->id),
            'presence' => $this->realtime->presenceForConversation($conversation->id),
            'totalUnread' => $this->realtime->totalUnread($userId),
        ];
    }

    /** @return array{id: string, name: string, manualStatus: string, customText: ?string, customEmoji: ?string} */
    public function authorizeConversationChannel(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId): array
    {
        $this->participant($userPublicId, $activeTeamPublicId, $conversationPublicId);
        $presence = $this->realtime->heartbeat($this->userId($userPublicId));

        return [
            'id' => $userPublicId,
            'name' => $presence->name,
            'manualStatus' => $presence->manualStatus->value,
            'customText' => $presence->customText,
            'customEmoji' => $presence->customEmoji,
        ];
    }

    public function authorizeUserChannel(string $userPublicId, string $activeTeamPublicId, string $channelUserPublicId): bool
    {
        $this->access->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::INDEX);

        return hash_equals($userPublicId, $channelUserPublicId);
    }

    public function heartbeat(string $userPublicId, string $activeTeamPublicId): PresenceSummary
    {
        $this->access->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::INDEX);
        $presence = $this->realtime->heartbeat($this->userId($userPublicId));
        $this->publisher->user($userPublicId, 'chat.presence.updated', $this->presencePayload($presence));

        return $presence;
    }

    public function updateStatus(string $userPublicId, string $activeTeamPublicId, ManualStatus $status, ?string $customText, ?string $customEmoji): PresenceSummary
    {
        $this->access->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::INDEX);
        $customText = $this->optionalText($customText, 120);
        $customEmoji = $this->optionalText($customEmoji, 16);
        $userId = $this->userId($userPublicId);
        $presence = $this->realtime->updateStatus($userId, $status, $customText, $customEmoji);
        $payload = $this->presencePayload($presence);
        $this->publisher->user($userPublicId, 'chat.presence.updated', $payload);

        foreach ($this->realtime->conversationPublicIdsForUser($userId) as $conversationPublicId) {
            $this->publisher->conversation($conversationPublicId, 'chat.presence.updated', $payload);
        }

        return $presence;
    }

    public function markDelivered(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId): void
    {
        [$conversation, $userId] = $this->participant($userPublicId, $activeTeamPublicId, $conversationPublicId);
        $message = $this->messageInConversation($messagePublicId, $conversation->id);
        $this->realtime->markDelivered($conversation->id, $userId, $message->id);
        $this->publishState($conversation, $userPublicId, $userId);
    }

    public function markRead(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId): void
    {
        [$conversation, $userId] = $this->participant($userPublicId, $activeTeamPublicId, $conversationPublicId);
        $message = $this->messageInConversation($messagePublicId, $conversation->id);
        $this->realtime->markRead($conversation->id, $userId, $message->id);
        $this->publishState($conversation, $userPublicId, $userId);
    }

    public function markUnread(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId): void
    {
        [$conversation, $userId] = $this->participant($userPublicId, $activeTeamPublicId, $conversationPublicId);
        $message = $this->messageInConversation($messagePublicId, $conversation->id);
        $this->realtime->markUnread($conversation->id, $userId, $message->id);
        $this->publishState($conversation, $userPublicId, $userId);
    }

    public function totalUnread(string $userPublicId, string $activeTeamPublicId): int
    {
        $this->access->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::INDEX);

        return $this->realtime->totalUnread($this->userId($userPublicId));
    }

    /** @return array{ConversationRecord, int} */
    private function participant(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId): array
    {
        $this->access->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::INDEX);
        $userId = $this->userId($userPublicId);
        $conversation = $this->conversations->findByPublicId($conversationPublicId) ?? throw MessageOperationDenied::notParticipant();
        $member = $this->conversations->hasActiveMembership($conversation->id, $userId);
        $allowed = $this->scopePolicy->allows(new ConversationScopeContext(
            type: $conversation->type,
            isConversationMember: $member,
            conversationTeamPublicId: $conversation->teamPublicId,
            activeTeamPublicId: $activeTeamPublicId,
            isMeetingParticipant: $conversation->type === ConversationType::Meeting && $member,
        ));

        if (! $allowed) {
            throw MessageOperationDenied::notParticipant();
        }

        return [$conversation, $userId];
    }

    private function userId(string $userPublicId): int
    {
        return $this->users->internalIdForPublicId($userPublicId) ?? throw MessageOperationDenied::notParticipant();
    }

    private function messageInConversation(string $messagePublicId, int $conversationId): MessageRecord
    {
        $message = $this->messages->findByPublicId($messagePublicId);

        if ($message === null || $message->conversationId !== $conversationId) {
            throw MessageOperationDenied::invalidReference();
        }

        return $message;
    }

    private function publishState(ConversationRecord $conversation, string $userPublicId, int $userId): void
    {
        $state = $this->realtime->state($conversation->id, $userId);
        $payload = [
            'conversationPublicId' => $conversation->publicId,
            'userPublicId' => $userPublicId,
            'lastDeliveredMessagePublicId' => $state->lastDeliveredMessagePublicId,
            'lastReadMessagePublicId' => $state->lastReadMessagePublicId,
            'firstUnreadMessagePublicId' => $state->firstUnreadMessagePublicId,
            'unreadCount' => $state->unreadCount,
            'totalUnread' => $this->realtime->totalUnread($userId),
        ];
        $this->publisher->conversation($conversation->publicId, 'chat.state.updated', $payload);
        $this->publisher->user($userPublicId, 'chat.unread.updated', $payload);
    }

    /** @return array<string, mixed> */
    private function presencePayload(PresenceSummary $presence): array
    {
        return [
            'userPublicId' => $presence->userPublicId,
            'name' => $presence->name,
            'online' => $presence->online,
            'lastSeenAt' => $presence->lastSeenAt?->format(DATE_ATOM),
            'manualStatus' => $presence->manualStatus->value,
            'customText' => $presence->customText,
            'customEmoji' => $presence->customEmoji,
        ];
    }

    private function optionalText(?string $value, int $maximum): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (mb_strlen($value) > $maximum) {
            throw new InvalidArgumentException('Chat status text is too long.');
        }

        return $value;
    }
}
