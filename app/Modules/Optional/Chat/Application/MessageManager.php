<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\MarkdownRenderer;
use App\Modules\Optional\Chat\Application\Contracts\MessageStore;
use App\Modules\Optional\Chat\Application\DTOs\ConversationRecord;
use App\Modules\Optional\Chat\Application\DTOs\MessageDraft;
use App\Modules\Optional\Chat\Application\DTOs\MessageRecord;
use App\Modules\Optional\Chat\Application\DTOs\MessageRevision;
use App\Modules\Optional\Chat\Application\DTOs\VisibleMessage;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopeContext;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopePolicy;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;
use App\Modules\Optional\Chat\Domain\Conversations\Exceptions\ConversationNotFound;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\MessageIdempotencyConflict;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\MessageOperationDenied;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\StaleMessageEdit;
use InvalidArgumentException;

final readonly class MessageManager
{
    private const MAX_BODY_LENGTH = 20_000;

    public function __construct(
        private MessageStore $messages,
        private ConversationStore $conversations,
        private ChatTransaction $transaction,
        private ChatModuleAccess $access,
        private UserLookup $users,
        private ConversationScopePolicy $scopePolicy,
        private MarkdownRenderer $markdown,
    ) {}

    /** @param list<string> $mentionedUserPublicIds */
    public function send(
        string $actorPublicId,
        string $activeTeamPublicId,
        string $conversationPublicId,
        string $body,
        string $clientMessageKey,
        ?string $replyToMessagePublicId = null,
        array $mentionedUserPublicIds = [],
    ): VisibleMessage {
        $body = $this->body($body);
        $clientMessageKey = $this->clientMessageKey($clientMessageKey);
        $mentionedUserPublicIds = array_values(array_unique($mentionedUserPublicIds));

        return $this->transaction->run(function () use ($actorPublicId, $activeTeamPublicId, $conversationPublicId, $body, $clientMessageKey, $replyToMessagePublicId, $mentionedUserPublicIds): VisibleMessage {
            [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId, true);
            $reply = $this->referencedMessage($replyToMessagePublicId, $conversation->id, $actorId);
            $mentionIds = $this->mentionIds($conversation, $mentionedUserPublicIds);
            [$everyone, $online] = $this->groupMentions($body);
            $hash = $this->requestHash($conversation->publicId, $body, $reply?->publicId, null, $mentionedUserPublicIds, $everyone, $online);
            $this->conversations->lockCanonicalKey('chat:message:'.$actorId.':'.$clientMessageKey);
            $existing = $this->messages->findByIdempotencyKey($actorId, $clientMessageKey);

            if ($existing !== null) {
                if ($existing->requestHash !== $hash) {
                    throw new MessageIdempotencyConflict;
                }

                return $this->visible($existing, $actorId);
            }

            $message = $this->messages->create($conversation->id, $actorId, $body, $reply?->id, null, $clientMessageKey, $hash);
            $this->messages->addRevision($message->id, 1, $body, $actorId);
            $this->messages->replaceMentions($message->id, $mentionIds, $everyone, $online);
            $this->messages->clearDraft($conversation->id, $actorId);

            return $this->visible($message, $actorId);
        });
    }

    /** @param list<string> $mentionedUserPublicIds */
    public function edit(
        string $actorPublicId,
        string $activeTeamPublicId,
        string $conversationPublicId,
        string $messagePublicId,
        int $expectedVersion,
        string $body,
        array $mentionedUserPublicIds = [],
    ): VisibleMessage {
        $body = $this->body($body);

        return $this->transaction->run(function () use ($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId, $expectedVersion, $body, $mentionedUserPublicIds): VisibleMessage {
            [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId, true);
            $message = $this->messageInConversation($messagePublicId, $conversation->id, true);

            if ($message->authorUserId !== $actorId) {
                throw MessageOperationDenied::notAuthor();
            }

            if ($this->messages->isHiddenForUser($message->id, $actorId)) {
                throw MessageOperationDenied::unavailableMessage();
            }

            if ($message->version !== $expectedVersion || ! $this->messages->updateBody($message->id, $expectedVersion, $body)) {
                throw new StaleMessageEdit;
            }

            $version = $expectedVersion + 1;
            $this->messages->addRevision($message->id, $version, $body, $actorId);
            $mentionIds = $this->mentionIds($conversation, $mentionedUserPublicIds);
            [$everyone, $online] = $this->groupMentions($body);
            $this->messages->replaceMentions($message->id, $mentionIds, $everyone, $online);

            return $this->visible($this->messages->findByPublicId($messagePublicId) ?? throw MessageOperationDenied::unavailableMessage(), $actorId);
        });
    }

    public function deleteForMe(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId): VisibleMessage
    {
        return $this->transaction->run(function () use ($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId): VisibleMessage {
            [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId, true);
            $message = $this->messageInConversation($messagePublicId, $conversation->id, true);
            $this->messages->hideForUser($message->id, $actorId);

            return $this->visible($message, $actorId);
        });
    }

    /** @return list<MessageRevision> */
    public function editHistory(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId): array
    {
        [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId);
        $message = $this->messageInConversation($messagePublicId, $conversation->id);

        if ($this->messages->isHiddenForUser($message->id, $actorId)) {
            throw MessageOperationDenied::unavailableMessage();
        }

        return $this->messages->revisions($message->id, $this->markdown);
    }

    public function react(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId, string $emoji): VisibleMessage
    {
        return $this->reaction($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId, $emoji, true);
    }

    public function removeReaction(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId, string $emoji): VisibleMessage
    {
        return $this->reaction($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId, $emoji, false);
    }

    public function forward(
        string $actorPublicId,
        string $activeTeamPublicId,
        string $sourceConversationPublicId,
        string $sourceMessagePublicId,
        string $destinationConversationPublicId,
        string $clientMessageKey,
    ): VisibleMessage {
        $clientMessageKey = $this->clientMessageKey($clientMessageKey);

        return $this->transaction->run(function () use ($actorPublicId, $activeTeamPublicId, $sourceConversationPublicId, $sourceMessagePublicId, $destinationConversationPublicId, $clientMessageKey): VisibleMessage {
            [$sourceConversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $sourceConversationPublicId);
            $source = $this->messageInConversation($sourceMessagePublicId, $sourceConversation->id);

            if ($this->messages->isHiddenForUser($source->id, $actorId)) {
                throw MessageOperationDenied::unavailableMessage();
            }

            [$destination] = $this->participant($actorPublicId, $activeTeamPublicId, $destinationConversationPublicId, true);
            $hash = $this->requestHash($destination->publicId, '', null, $source->publicId, [], false, false);
            $this->conversations->lockCanonicalKey('chat:message:'.$actorId.':'.$clientMessageKey);
            $existing = $this->messages->findByIdempotencyKey($actorId, $clientMessageKey);

            if ($existing !== null) {
                if ($existing->requestHash !== $hash) {
                    throw new MessageIdempotencyConflict;
                }

                return $this->visible($existing, $actorId);
            }

            $message = $this->messages->create($destination->id, $actorId, $source->body, null, $source->id, $clientMessageKey, $hash);
            $this->messages->addRevision($message->id, 1, $source->body, $actorId);

            return $this->visible($message, $actorId);
        });
    }

    public function pin(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId): VisibleMessage
    {
        return $this->setPin($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId, true);
    }

    public function unpin(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId): VisibleMessage
    {
        return $this->setPin($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId, false);
    }

    public function bookmark(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId): VisibleMessage
    {
        return $this->setBookmark($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId, true);
    }

    public function removeBookmark(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId): VisibleMessage
    {
        return $this->setBookmark($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId, false);
    }

    public function saveDraft(
        string $actorPublicId,
        string $activeTeamPublicId,
        string $conversationPublicId,
        string $body,
        ?string $replyToMessagePublicId = null,
    ): ?MessageDraft {
        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw new InvalidArgumentException('A Chat draft cannot exceed 20000 characters.');
        }

        return $this->transaction->run(function () use ($actorPublicId, $activeTeamPublicId, $conversationPublicId, $body, $replyToMessagePublicId): ?MessageDraft {
            [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId, true);
            $this->conversations->lockCanonicalKey('chat:draft:'.$conversation->id.':'.$actorId);

            if ($body === '' && $replyToMessagePublicId === null) {
                $this->messages->clearDraft($conversation->id, $actorId);

                return null;
            }

            $reply = $this->referencedMessage($replyToMessagePublicId, $conversation->id, $actorId);

            return $this->messages->saveDraft($conversation->id, $actorId, $body, $reply?->id);
        });
    }

    public function draft(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId): ?MessageDraft
    {
        [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId);

        return $this->messages->draft($conversation->id, $actorId);
    }

    /** @return list<VisibleMessage> */
    public function messages(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId): array
    {
        [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId);

        return array_map(fn (MessageRecord $message): VisibleMessage => $this->visible($message, $actorId), $this->messages->conversationMessages($conversation->id));
    }

    private function reaction(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId, string $emoji, bool $add): VisibleMessage
    {
        $emoji = $this->emoji($emoji);

        return $this->transaction->run(function () use ($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId, $emoji, $add): VisibleMessage {
            [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId, true);
            $message = $this->messageInConversation($messagePublicId, $conversation->id, true);
            $this->ensureVisible($message, $actorId);

            if ($add) {
                $this->messages->addReaction($message->id, $actorId, $emoji);
            } else {
                $this->messages->removeReaction($message->id, $actorId, $emoji);
            }

            return $this->visible($message, $actorId);
        });
    }

    private function setPin(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId, bool $pin): VisibleMessage
    {
        return $this->transaction->run(function () use ($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId, $pin): VisibleMessage {
            [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId, true);
            $message = $this->messageInConversation($messagePublicId, $conversation->id, true);
            $this->ensureVisible($message, $actorId);
            $pin ? $this->messages->pin($message->id, $actorId) : $this->messages->unpin($message->id);

            return $this->visible($message, $actorId);
        });
    }

    private function setBookmark(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $messagePublicId, bool $bookmark): VisibleMessage
    {
        return $this->transaction->run(function () use ($actorPublicId, $activeTeamPublicId, $conversationPublicId, $messagePublicId, $bookmark): VisibleMessage {
            [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId, true);
            $message = $this->messageInConversation($messagePublicId, $conversation->id);
            $this->ensureVisible($message, $actorId);
            $bookmark ? $this->messages->bookmark($message->id, $actorId) : $this->messages->removeBookmark($message->id, $actorId);

            return $this->visible($message, $actorId);
        });
    }

    /** @return array{ConversationRecord, int} */
    private function participant(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId, bool $lock = false): array
    {
        $this->access->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::INDEX);
        $userId = $this->users->internalIdForPublicId($userPublicId) ?? throw MessageOperationDenied::notParticipant();
        $conversation = $this->conversations->findByPublicId($conversationPublicId, $lock) ?? throw new ConversationNotFound;
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

    private function messageInConversation(string $messagePublicId, int $conversationId, bool $lock = false): MessageRecord
    {
        $message = $this->messages->findByPublicId($messagePublicId, $lock) ?? throw MessageOperationDenied::unavailableMessage();

        if ($message->conversationId !== $conversationId) {
            throw MessageOperationDenied::invalidReference();
        }

        return $message;
    }

    private function referencedMessage(?string $messagePublicId, int $conversationId, int $viewerId): ?MessageRecord
    {
        if ($messagePublicId === null) {
            return null;
        }

        $message = $this->messageInConversation($messagePublicId, $conversationId);
        $this->ensureVisible($message, $viewerId);

        return $message;
    }

    /**
     * @param  list<string>  $publicIds
     * @return list<int>
     */
    private function mentionIds(ConversationRecord $conversation, array $publicIds): array
    {
        $ids = [];
        $activePublicIds = [];

        foreach ($this->users->allActiveDisplaySummaries() as $summary) {
            $activePublicIds[$summary->publicId] = true;
        }

        foreach (array_values(array_unique($publicIds)) as $publicId) {
            $userId = $this->users->internalIdForPublicId($publicId);

            if (! isset($activePublicIds[$publicId]) || $userId === null || ! $this->conversations->hasActiveMembership($conversation->id, $userId)) {
                throw MessageOperationDenied::invalidMention();
            }

            $ids[] = $userId;
        }

        return $ids;
    }

    private function visible(MessageRecord $message, int $viewerId): VisibleMessage
    {
        $hidden = $this->messages->isHiddenForUser($message->id, $viewerId);
        $reply = $message->replyToMessageId === null ? null : $this->messages->findById($message->replyToMessageId);
        $replyVisible = $reply !== null && ! $this->messages->isHiddenForUser($reply->id, $viewerId);
        $mentions = $this->messages->mentions($message->id);
        $mentionedPublicIds = [];

        foreach ($mentions['userIds'] as $userId) {
            $publicId = $this->users->publicIdForInternalId($userId);

            if ($publicId !== null) {
                $mentionedPublicIds[] = $publicId;
            }
        }

        return new VisibleMessage(
            publicId: $message->publicId,
            authorPublicId: $this->users->publicIdForInternalId($message->authorUserId) ?? '',
            body: $hidden ? null : $message->body,
            renderedHtml: $hidden ? null : $this->markdown->render($message->body),
            replyToMessagePublicId: $hidden || ! $replyVisible ? null : $reply->publicId,
            forwarded: ! $hidden && $message->forwardedFromMessageId !== null,
            version: $message->version,
            edited: $message->editedAt !== null,
            deletedForViewer: $hidden,
            pinned: ! $hidden && $this->messages->isPinned($message->id),
            bookmarked: ! $hidden && $this->messages->isBookmarked($message->id, $viewerId),
            reactions: $hidden ? [] : $this->messages->reactions($message->id),
            mentionedUserPublicIds: $hidden ? [] : $mentionedPublicIds,
            mentionsEveryone: ! $hidden && $mentions['everyone'],
            mentionsOnline: ! $hidden && $mentions['online'],
            createdAt: $message->createdAt,
        );
    }

    private function ensureVisible(MessageRecord $message, int $viewerId): void
    {
        if ($this->messages->isHiddenForUser($message->id, $viewerId)) {
            throw MessageOperationDenied::unavailableMessage();
        }
    }

    private function body(string $body): string
    {
        $body = trim(str_replace("\r\n", "\n", $body));

        if ($body === '' || mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw new InvalidArgumentException('A Chat message must contain between 1 and 20000 characters.');
        }

        return $body;
    }

    private function clientMessageKey(string $key): string
    {
        $key = trim($key);

        if ($key === '' || mb_strlen($key) > 120) {
            throw new InvalidArgumentException('A Chat client message key must contain between 1 and 120 characters.');
        }

        return $key;
    }

    private function emoji(string $emoji): string
    {
        $emoji = trim($emoji);

        if ($emoji === '' || mb_strlen($emoji) > 32 || preg_match('/[\p{C}\p{Z}]/u', $emoji) === 1 || preg_match('/[\p{Extended_Pictographic}\p{Regional_Indicator}\x{20E3}]/u', $emoji) !== 1) {
            throw new InvalidArgumentException('A reaction must be a valid emoji.');
        }

        return $emoji;
    }

    /** @return array{bool, bool} */
    private function groupMentions(string $body): array
    {
        return [
            preg_match('/(?<![\pL\pN_])@everyone\b/ui', $body) === 1,
            preg_match('/(?<![\pL\pN_])@online\b/ui', $body) === 1,
        ];
    }

    /** @param list<string> $mentionedUserPublicIds */
    private function requestHash(string $conversationPublicId, string $body, ?string $replyPublicId, ?string $forwardedPublicId, array $mentionedUserPublicIds, bool $everyone, bool $online): string
    {
        sort($mentionedUserPublicIds);

        return hash('sha256', json_encode([
            'conversation' => $conversationPublicId,
            'body' => $body,
            'reply' => $replyPublicId,
            'forwarded' => $forwardedPublicId,
            'mentions' => $mentionedUserPublicIds,
            'everyone' => $everyone,
            'online' => $online,
        ], JSON_THROW_ON_ERROR));
    }
}
