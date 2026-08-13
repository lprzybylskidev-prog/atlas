<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\MessageDraft;
use App\Modules\Optional\Chat\Application\DTOs\MessageReaction;
use App\Modules\Optional\Chat\Application\DTOs\MessageRecord;
use App\Modules\Optional\Chat\Application\DTOs\MessageRevision;

interface MessageStore
{
    public function findByPublicId(string $publicId, bool $lock = false): ?MessageRecord;

    public function findById(int $id): ?MessageRecord;

    public function findByIdempotencyKey(int $authorUserId, string $clientMessageKey): ?MessageRecord;

    public function create(
        int $conversationId,
        int $authorUserId,
        string $body,
        ?int $replyToMessageId,
        ?int $forwardedFromMessageId,
        string $clientMessageKey,
        string $requestHash,
    ): MessageRecord;

    public function updateBody(int $messageId, int $expectedVersion, string $body): bool;

    public function addRevision(int $messageId, int $version, string $body, int $editedByUserId): void;

    /** @return list<MessageRevision> */
    public function revisions(int $messageId, MarkdownRenderer $renderer): array;

    /** @return list<MessageRecord> */
    public function conversationMessages(int $conversationId): array;

    public function hideForUser(int $messageId, int $userId): void;

    public function isHiddenForUser(int $messageId, int $userId): bool;

    public function addReaction(int $messageId, int $userId, string $emoji): void;

    public function removeReaction(int $messageId, int $userId, string $emoji): void;

    /** @return list<MessageReaction> */
    public function reactions(int $messageId): array;

    /** @param list<int> $mentionedUserIds */
    public function replaceMentions(int $messageId, array $mentionedUserIds, bool $everyone, bool $online): void;

    /** @return array{userIds: list<int>, everyone: bool, online: bool} */
    public function mentions(int $messageId): array;

    public function pin(int $messageId, int $userId): void;

    public function unpin(int $messageId): void;

    public function isPinned(int $messageId): bool;

    public function bookmark(int $messageId, int $userId): void;

    public function removeBookmark(int $messageId, int $userId): void;

    public function isBookmarked(int $messageId, int $userId): bool;

    public function saveDraft(int $conversationId, int $userId, string $body, ?int $replyToMessageId): MessageDraft;

    public function draft(int $conversationId, int $userId): ?MessageDraft;

    public function clearDraft(int $conversationId, int $userId): void;
}
