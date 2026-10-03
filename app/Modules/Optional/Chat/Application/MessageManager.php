<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Contracts\AttachmentStore;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\MarkdownRenderer;
use App\Modules\Optional\Chat\Application\Contracts\MessageStore;
use App\Modules\Optional\Chat\Application\DTOs\ConversationRecord;
use App\Modules\Optional\Chat\Application\DTOs\MessageDraft;
use App\Modules\Optional\Chat\Application\DTOs\MessageRecord;
use App\Modules\Optional\Chat\Application\DTOs\MessageRevision;
use App\Modules\Optional\Chat\Application\DTOs\VisibleMessage;
use App\Modules\Optional\Chat\Application\DTOs\VisibleMessagePage;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopeContext;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopePolicy;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;
use App\Modules\Optional\Chat\Domain\Conversations\Exceptions\ConversationNotFound;
use App\Modules\Optional\Chat\Domain\Messages\AttachmentKind;
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
        private ?AttachmentStore $attachments = null,
        private ?ChatSearchProjectionUpdater $search = null,
    ) {}

    /**
     * @param  list<string>  $mentionedUserPublicIds
     * @param  list<string>  $attachmentPublicIds
     */
    public function send(
        string $actorPublicId,
        string $activeTeamPublicId,
        string $conversationPublicId,
        string $body,
        string $clientMessageKey,
        ?string $replyToMessagePublicId = null,
        array $mentionedUserPublicIds = [],
        array $attachmentPublicIds = [],
    ): VisibleMessage {
        $attachmentPublicIds = array_values(array_unique($attachmentPublicIds));
        $body = $this->body($body, $attachmentPublicIds !== []);
        $clientMessageKey = $this->clientMessageKey($clientMessageKey);
        $mentionedUserPublicIds = array_values(array_unique($mentionedUserPublicIds));

        return $this->transaction->run(function () use ($actorPublicId, $activeTeamPublicId, $conversationPublicId, $body, $clientMessageKey, $replyToMessagePublicId, $mentionedUserPublicIds, $attachmentPublicIds): VisibleMessage {
            [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId, true);
            $attachmentIds = $this->sendableAttachments($attachmentPublicIds, $conversation->id, $actorId, $actorPublicId, $activeTeamPublicId);
            $reply = $this->referencedMessage($replyToMessagePublicId, $conversation->id, $actorId);
            $mentionIds = $this->mentionIds($conversation, $mentionedUserPublicIds);
            [$everyone, $online] = $this->groupMentions($body);
            $hash = $this->requestHash($conversation->publicId, $body, $reply?->publicId, null, $mentionedUserPublicIds, $everyone, $online, $attachmentPublicIds);
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
            $this->attachments?->attachToMessage($attachmentIds, $message->id);
            $this->messages->clearDraft($conversation->id, $actorId);
            $this->search?->refresh('message', $message->publicId);

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
            $this->search?->refresh('message', $message->publicId);

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
            $this->search?->refresh('message', $message->publicId);

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
        return $this->messagePage($actorPublicId, $activeTeamPublicId, $conversationPublicId)->messages;
    }

    public function messagePage(
        string $actorPublicId,
        string $activeTeamPublicId,
        string $conversationPublicId,
        ?string $beforeMessagePublicId = null,
        ?string $afterMessagePublicId = null,
        int $limit = 50,
    ): VisibleMessagePage {
        [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId);
        $beforeId = $beforeMessagePublicId === null ? null : $this->messageInConversation($beforeMessagePublicId, $conversation->id)->id;
        $afterId = $afterMessagePublicId === null ? null : $this->messageInConversation($afterMessagePublicId, $conversation->id)->id;
        $page = $this->messages->conversationMessagesPage($conversation->id, $limit, $beforeId, $afterId);

        return new VisibleMessagePage(
            messages: $this->visibleMany($page->messages, $actorId),
            hasOlder: $page->hasOlder,
            hasNewer: $page->hasNewer,
        );
    }

    /** @return iterable<list<VisibleMessage>> */
    public function messageBatches(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, int $limit = 50): iterable
    {
        [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId);
        $afterId = 0;

        do {
            $page = $this->messages->conversationMessagesPage($conversation->id, $limit, afterMessageId: $afterId);
            if ($page->messages === []) {
                return;
            }

            yield $this->visibleMany($page->messages, $actorId);
            $afterId = $page->messages[array_key_last($page->messages)]->id;
        } while ($page->hasNewer);
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
        return $this->visibleMany([$message], $viewerId)[0];
    }

    /**
     * @param  list<MessageRecord>  $messages
     * @return list<VisibleMessage>
     */
    private function visibleMany(array $messages, int $viewerId): array
    {
        if ($messages === []) {
            return [];
        }

        $states = $this->messages->presentationStates($messages, $viewerId);
        $replyIds = array_values(array_unique(array_filter(array_map(
            static fn (MessageRecord $message): ?int => $message->replyToMessageId,
            $messages,
        ))));
        $replies = $this->messages->findByIds($replyIds);
        $userIds = array_map(static fn (MessageRecord $message): int => $message->authorUserId, $messages);
        $users = $this->users->displaySummariesForInternalIds(array_values(array_unique($userIds)));
        $attachments = $this->attachments?->forMessages(array_map(static fn (MessageRecord $message): int => $message->id, $messages)) ?? [];
        $visible = [];

        foreach ($messages as $message) {
            $state = $states[$message->id] ?? throw new InvalidArgumentException('Missing Chat message presentation state.');
            $reply = $message->replyToMessageId === null ? null : ($replies[$message->replyToMessageId] ?? null);
            $hidden = $state->hidden;

            $visible[] = new VisibleMessage(
                publicId: $message->publicId,
                authorPublicId: $users[$message->authorUserId]->publicId ?? '',
                body: $hidden ? null : $message->body,
                renderedHtml: $hidden ? null : $this->markdown->render($message->body),
                replyToMessagePublicId: $hidden || $state->replyHidden ? null : $reply?->publicId,
                forwarded: ! $hidden && $message->forwardedFromMessageId !== null,
                version: $message->version,
                edited: $message->editedAt !== null,
                deletedForViewer: $hidden,
                pinned: ! $hidden && $state->pinned,
                bookmarked: ! $hidden && $state->bookmarked,
                reactions: $hidden ? [] : $state->reactions,
                mentionedUserPublicIds: $hidden ? [] : $state->mentionedUserPublicIds,
                mentionsEveryone: ! $hidden && $state->mentionsEveryone,
                mentionsOnline: ! $hidden && $state->mentionsOnline,
                createdAt: $message->createdAt,
                attachments: $hidden ? [] : ($attachments[$message->id] ?? []),
            );
        }

        return $visible;
    }

    private function ensureVisible(MessageRecord $message, int $viewerId): void
    {
        if ($this->messages->isHiddenForUser($message->id, $viewerId)) {
            throw MessageOperationDenied::unavailableMessage();
        }
    }

    private function body(string $body, bool $allowEmpty = false): string
    {
        $body = trim(str_replace("\r\n", "\n", $body));

        if ((! $allowEmpty && $body === '') || mb_strlen($body) > self::MAX_BODY_LENGTH) {
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

    /**
     * @param  list<string>  $mentionedUserPublicIds
     * @param  list<string>  $attachmentPublicIds
     */
    private function requestHash(string $conversationPublicId, string $body, ?string $replyPublicId, ?string $forwardedPublicId, array $mentionedUserPublicIds, bool $everyone, bool $online, array $attachmentPublicIds = []): string
    {
        sort($mentionedUserPublicIds);
        sort($attachmentPublicIds);

        return hash('sha256', json_encode([
            'conversation' => $conversationPublicId,
            'body' => $body,
            'reply' => $replyPublicId,
            'forwarded' => $forwardedPublicId,
            'mentions' => $mentionedUserPublicIds,
            'everyone' => $everyone,
            'online' => $online,
            'attachments' => $attachmentPublicIds,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<string>  $publicIds
     * @return list<int>
     */
    private function sendableAttachments(array $publicIds, int $conversationId, int $actorId, string $actorPublicId, string $activeTeamPublicId): array
    {
        if ($publicIds === []) {
            return [];
        }

        if ($this->attachments === null) {
            throw MessageOperationDenied::invalidReference();
        }

        $ids = [];
        foreach ($publicIds as $publicId) {
            $attachment = $this->attachments->findByPublicId($publicId, true);

            if ($attachment === null || $attachment->conversationId !== $conversationId || $attachment->uploaderUserId !== $actorId || $attachment->messageId !== null || $attachment->discarded) {
                throw MessageOperationDenied::invalidReference();
            }

            $permission = $attachment->kind === AttachmentKind::Voice ? ChatPermissionCatalog::VOICE_MESSAGE_STORE : ChatPermissionCatalog::ATTACHMENT_STORE;
            $this->access->ensureAllowed($actorPublicId, $activeTeamPublicId, $permission);
            $ids[] = $attachment->id;
        }

        return $ids;
    }
}
