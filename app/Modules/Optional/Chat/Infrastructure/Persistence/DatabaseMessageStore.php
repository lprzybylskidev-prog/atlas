<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Contracts\MarkdownRenderer;
use App\Modules\Optional\Chat\Application\Contracts\MessageStore;
use App\Modules\Optional\Chat\Application\DTOs\MessageDraft;
use App\Modules\Optional\Chat\Application\DTOs\MessagePresentationState;
use App\Modules\Optional\Chat\Application\DTOs\MessageReaction;
use App\Modules\Optional\Chat\Application\DTOs\MessageRecord;
use App\Modules\Optional\Chat\Application\DTOs\MessageRecordPage;
use App\Modules\Optional\Chat\Application\DTOs\MessageRevision;
use App\Modules\Optional\Chat\Domain\Messages\MentionType;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use stdClass;
use UnexpectedValueException;

final readonly class DatabaseMessageStore implements MessageStore
{
    public function __construct(
        private ConnectionInterface $database,
        private UserLookup $users,
    ) {}

    public function findByPublicId(string $publicId, bool $lock = false): ?MessageRecord
    {
        $query = $this->database->table(ChatDatabaseTable::MESSAGES)->where('public_id', $publicId);

        return $this->message($lock ? $query->lockForUpdate()->first() : $query->first());
    }

    public function findById(int $id): ?MessageRecord
    {
        return $this->message($this->database->table(ChatDatabaseTable::MESSAGES)->where('id', $id)->first());
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, MessageRecord>
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $messages = [];
        foreach ($this->database->table(ChatDatabaseTable::MESSAGES)->whereIn('id', $ids)->get() as $row) {
            $message = $this->message($row);
            if ($message !== null) {
                $messages[$message->id] = $message;
            }
        }

        return $messages;
    }

    public function findByIdempotencyKey(int $authorUserId, string $clientMessageKey): ?MessageRecord
    {
        return $this->message($this->database->table(ChatDatabaseTable::MESSAGES)
            ->where('author_user_id', $authorUserId)
            ->where('client_message_key', $clientMessageKey)
            ->first());
    }

    public function create(
        int $conversationId,
        int $authorUserId,
        string $body,
        ?int $replyToMessageId,
        ?int $forwardedFromMessageId,
        string $clientMessageKey,
        string $requestHash,
    ): MessageRecord {
        $now = now();
        $publicId = (string) Str::ulid();
        $id = $this->database->table(ChatDatabaseTable::MESSAGES)->insertGetId([
            'public_id' => $publicId,
            'conversation_id' => $conversationId,
            'author_user_id' => $authorUserId,
            'body' => $body,
            'reply_to_message_id' => $replyToMessageId,
            'forwarded_from_message_id' => $forwardedFromMessageId,
            'client_message_key' => $clientMessageKey,
            'request_hash' => $requestHash,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById((int) $id) ?? throw new UnexpectedValueException('Created Chat message could not be loaded.');
    }

    public function updateBody(int $messageId, int $expectedVersion, string $body): bool
    {
        return $this->database->table(ChatDatabaseTable::MESSAGES)
            ->where('id', $messageId)
            ->where('version', $expectedVersion)
            ->update([
                'body' => $body,
                'version' => $expectedVersion + 1,
                'edited_at' => now(),
                'updated_at' => now(),
            ]) === 1;
    }

    public function addRevision(int $messageId, int $version, string $body, int $editedByUserId): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_EDIT_HISTORY)->insert([
            'message_id' => $messageId,
            'version' => $version,
            'body' => $body,
            'edited_by_user_id' => $editedByUserId,
            'created_at' => now(),
        ]);
    }

    public function revisions(int $messageId, MarkdownRenderer $renderer): array
    {
        $revisions = [];

        foreach ($this->database->table(ChatDatabaseTable::MESSAGE_EDIT_HISTORY)
            ->where('message_id', $messageId)
            ->orderBy('version')
            ->get() as $row) {
            $values = get_object_vars($row);
            $body = $this->stringAllowEmpty($values, 'body');
            $revisions[] = new MessageRevision(
                version: $this->int($values, 'version'),
                body: $body,
                renderedHtml: $renderer->render($body),
                createdAt: $this->date($values, 'created_at'),
            );
        }

        return $revisions;
    }

    public function conversationMessagesPage(int $conversationId, int $limit, ?int $beforeMessageId = null, ?int $afterMessageId = null): MessageRecordPage
    {
        if ($limit < 1 || $limit > 100 || ($beforeMessageId !== null && $afterMessageId !== null)) {
            throw new UnexpectedValueException('Invalid Chat message page request.');
        }

        $query = $this->database->table(ChatDatabaseTable::MESSAGES)->where('conversation_id', $conversationId);
        $descending = $afterMessageId === null;

        if ($beforeMessageId !== null) {
            $query->where('id', '<', $beforeMessageId);
        } elseif ($afterMessageId !== null) {
            $query->where('id', '>', $afterMessageId);
        }

        $rows = $query->orderBy('id', $descending ? 'desc' : 'asc')->limit($limit + 1)->get()->all();
        $hasExtra = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        if ($descending) {
            $rows = array_reverse($rows);
        }

        $messages = [];
        foreach ($rows as $row) {
            $messages[] = $this->message($row) ?? throw new UnexpectedValueException('Invalid Chat message row.');
        }

        return new MessageRecordPage(
            messages: $messages,
            hasOlder: $descending && $hasExtra,
            hasNewer: ! $descending && $hasExtra,
        );
    }

    public function presentationStates(array $messages, int $viewerUserId): array
    {
        if ($messages === []) {
            return [];
        }

        $messageIds = array_map(static fn (MessageRecord $message): int => $message->id, $messages);
        $replyIds = array_values(array_unique(array_filter(array_map(
            static fn (MessageRecord $message): ?int => $message->replyToMessageId,
            $messages,
        ))));
        $hiddenIds = $this->integerSet($this->database->table(ChatDatabaseTable::MESSAGE_DELETIONS)
            ->where('user_id', $viewerUserId)
            ->whereIn('message_id', [...$messageIds, ...$replyIds])
            ->pluck('message_id')
            ->all());
        $pinnedIds = $this->integerSet($this->database->table(ChatDatabaseTable::MESSAGE_PINS)
            ->whereIn('message_id', $messageIds)
            ->pluck('message_id')
            ->all());
        $bookmarkedIds = $this->integerSet($this->database->table(ChatDatabaseTable::MESSAGE_BOOKMARKS)
            ->where('user_id', $viewerUserId)
            ->whereIn('message_id', $messageIds)
            ->pluck('message_id')
            ->all());
        $mentionRows = $this->database->table(ChatDatabaseTable::MESSAGE_MENTIONS)
            ->whereIn('message_id', $messageIds)
            ->orderBy('id')
            ->get();
        $reactionRows = $this->database->table(ChatDatabaseTable::MESSAGE_REACTIONS)
            ->whereIn('message_id', $messageIds)
            ->orderBy('id')
            ->get();
        $userIds = [];

        foreach ([$mentionRows, $reactionRows] as $rows) {
            foreach ($rows as $row) {
                $userId = $row->mentioned_user_id ?? $row->user_id ?? null;
                if (is_numeric($userId)) {
                    $userIds[] = (int) $userId;
                }
            }
        }

        $users = $this->users->displaySummariesForInternalIds(array_values(array_unique($userIds)));
        $mentions = [];
        foreach ($mentionRows as $row) {
            $values = get_object_vars($row);
            $messageId = $this->int($values, 'message_id');
            $type = MentionType::from($this->string($values, 'type'));
            $mentions[$messageId] ??= ['users' => [], 'everyone' => false, 'online' => false];
            if ($type === MentionType::User && is_numeric($row->mentioned_user_id ?? null)) {
                $summary = $users[(int) $row->mentioned_user_id] ?? null;
                if ($summary !== null) {
                    $mentions[$messageId]['users'][] = $summary->publicId;
                }
            } elseif ($type === MentionType::Everyone) {
                $mentions[$messageId]['everyone'] = true;
            } elseif ($type === MentionType::Online) {
                $mentions[$messageId]['online'] = true;
            }
        }

        $reactions = [];
        foreach ($reactionRows as $row) {
            $values = get_object_vars($row);
            $messageId = $this->int($values, 'message_id');
            $userId = $this->int($values, 'user_id');
            $summary = $users[$userId] ?? null;
            if ($summary !== null) {
                $reactions[$messageId][] = new MessageReaction($this->string($values, 'emoji'), $summary->publicId);
            }
        }

        $states = [];
        foreach ($messages as $message) {
            $mention = $mentions[$message->id] ?? ['users' => [], 'everyone' => false, 'online' => false];
            $states[$message->id] = new MessagePresentationState(
                hidden: isset($hiddenIds[$message->id]),
                replyHidden: $message->replyToMessageId !== null && isset($hiddenIds[$message->replyToMessageId]),
                pinned: isset($pinnedIds[$message->id]),
                bookmarked: isset($bookmarkedIds[$message->id]),
                reactions: $reactions[$message->id] ?? [],
                mentionedUserPublicIds: $mention['users'],
                mentionsEveryone: $mention['everyone'],
                mentionsOnline: $mention['online'],
            );
        }

        return $states;
    }

    public function hideForUser(int $messageId, int $userId): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_DELETIONS)->insertOrIgnore([
            'message_id' => $messageId,
            'user_id' => $userId,
            'deleted_at' => now(),
        ]);
        $this->removeBookmark($messageId, $userId);
    }

    /**
     * @param  list<int>  $messageIds
     * @return array<int, true>
     */
    public function hiddenMessageIds(array $messageIds, int $userId): array
    {
        if ($messageIds === []) {
            return [];
        }

        return $this->integerSet($this->database->table(ChatDatabaseTable::MESSAGE_DELETIONS)
            ->where('user_id', $userId)
            ->whereIn('message_id', $messageIds)
            ->pluck('message_id')
            ->all());
    }

    public function isHiddenForUser(int $messageId, int $userId): bool
    {
        return $this->database->table(ChatDatabaseTable::MESSAGE_DELETIONS)
            ->where('message_id', $messageId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function addReaction(int $messageId, int $userId, string $emoji): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_REACTIONS)->insertOrIgnore([
            'message_id' => $messageId,
            'user_id' => $userId,
            'emoji' => $emoji,
            'created_at' => now(),
        ]);
    }

    public function removeReaction(int $messageId, int $userId, string $emoji): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_REACTIONS)
            ->where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->delete();
    }

    public function reactions(int $messageId): array
    {
        $reactions = [];

        foreach ($this->database->table(ChatDatabaseTable::MESSAGE_REACTIONS)->where('message_id', $messageId)->orderBy('id')->get() as $row) {
            $values = get_object_vars($row);
            $userPublicId = $this->users->publicIdForInternalId($this->int($values, 'user_id'));

            if ($userPublicId !== null) {
                $reactions[] = new MessageReaction($this->string($values, 'emoji'), $userPublicId);
            }
        }

        return $reactions;
    }

    public function replaceMentions(int $messageId, array $mentionedUserIds, bool $everyone, bool $online): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_MENTIONS)->where('message_id', $messageId)->delete();
        $now = now();

        foreach (array_values(array_unique($mentionedUserIds)) as $userId) {
            $this->database->table(ChatDatabaseTable::MESSAGE_MENTIONS)->insert([
                'message_id' => $messageId,
                'type' => MentionType::User->value,
                'mentioned_user_id' => $userId,
                'created_at' => $now,
            ]);
        }

        foreach ([MentionType::Everyone->value => $everyone, MentionType::Online->value => $online] as $type => $enabled) {
            if ($enabled) {
                $this->database->table(ChatDatabaseTable::MESSAGE_MENTIONS)->insert([
                    'message_id' => $messageId,
                    'type' => $type,
                    'mentioned_user_id' => null,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function mentions(int $messageId): array
    {
        $userIds = [];
        $everyone = false;
        $online = false;

        foreach ($this->database->table(ChatDatabaseTable::MESSAGE_MENTIONS)->where('message_id', $messageId)->get() as $row) {
            $values = get_object_vars($row);
            $type = MentionType::from($this->string($values, 'type'));

            if ($type === MentionType::User) {
                $userIds[] = $this->int($values, 'mentioned_user_id');
            } elseif ($type === MentionType::Everyone) {
                $everyone = true;
            } else {
                $online = true;
            }
        }

        return ['userIds' => $userIds, 'everyone' => $everyone, 'online' => $online];
    }

    public function pin(int $messageId, int $userId): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_PINS)->insertOrIgnore([
            'message_id' => $messageId,
            'pinned_by_user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    public function unpin(int $messageId): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_PINS)->where('message_id', $messageId)->delete();
    }

    public function isPinned(int $messageId): bool
    {
        return $this->database->table(ChatDatabaseTable::MESSAGE_PINS)->where('message_id', $messageId)->exists();
    }

    public function bookmark(int $messageId, int $userId): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_BOOKMARKS)->insertOrIgnore([
            'message_id' => $messageId,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    public function removeBookmark(int $messageId, int $userId): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_BOOKMARKS)
            ->where('message_id', $messageId)
            ->where('user_id', $userId)
            ->delete();
    }

    public function isBookmarked(int $messageId, int $userId): bool
    {
        return $this->database->table(ChatDatabaseTable::MESSAGE_BOOKMARKS)
            ->where('message_id', $messageId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function saveDraft(int $conversationId, int $userId, string $body, ?int $replyToMessageId): MessageDraft
    {
        $existing = $this->database->table(ChatDatabaseTable::MESSAGE_DRAFTS)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first();
        $now = now();

        if ($existing instanceof stdClass) {
            $values = get_object_vars($existing);
            $this->database->table(ChatDatabaseTable::MESSAGE_DRAFTS)->where('id', $this->int($values, 'id'))->update([
                'body' => $body,
                'reply_to_message_id' => $replyToMessageId,
                'version' => $this->int($values, 'version') + 1,
                'updated_at' => $now,
            ]);
        } else {
            $this->database->table(ChatDatabaseTable::MESSAGE_DRAFTS)->insert([
                'public_id' => (string) Str::ulid(),
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'body' => $body,
                'reply_to_message_id' => $replyToMessageId,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->draft($conversationId, $userId) ?? throw new UnexpectedValueException('Saved Chat draft could not be loaded.');
    }

    public function draft(int $conversationId, int $userId): ?MessageDraft
    {
        $row = $this->database->table(ChatDatabaseTable::MESSAGE_DRAFTS)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        $values = get_object_vars($row);
        $replyId = $this->nullableInt($values['reply_to_message_id'] ?? null);

        return new MessageDraft(
            publicId: $this->string($values, 'public_id'),
            body: $this->stringAllowEmpty($values, 'body'),
            replyToMessagePublicId: $replyId === null ? null : $this->findById($replyId)?->publicId,
            version: $this->int($values, 'version'),
            updatedAt: $this->date($values, 'updated_at'),
        );
    }

    public function clearDraft(int $conversationId, int $userId): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_DRAFTS)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->delete();
    }

    private function message(?stdClass $row): ?MessageRecord
    {
        if (! $row instanceof stdClass) {
            return null;
        }

        $values = get_object_vars($row);

        return new MessageRecord(
            id: $this->int($values, 'id'),
            publicId: $this->string($values, 'public_id'),
            conversationId: $this->int($values, 'conversation_id'),
            authorUserId: $this->int($values, 'author_user_id'),
            body: $this->stringAllowEmpty($values, 'body'),
            replyToMessageId: $this->nullableInt($values['reply_to_message_id'] ?? null),
            forwardedFromMessageId: $this->nullableInt($values['forwarded_from_message_id'] ?? null),
            clientMessageKey: $this->string($values, 'client_message_key'),
            requestHash: $this->string($values, 'request_hash'),
            version: $this->int($values, 'version'),
            editedAt: $this->nullableDate($values['edited_at'] ?? null),
            createdAt: $this->date($values, 'created_at'),
        );
    }

    /** @param array<mixed> $values */
    private function int(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (! is_numeric($value)) {
            throw new UnexpectedValueException(sprintf('Chat persistence field [%s] must be an integer.', $key));
        }

        return (int) $value;
    }

    /** @param array<mixed> $values */
    private function string(array $values, string $key): string
    {
        $value = $this->stringAllowEmpty($values, $key);

        if ($value === '') {
            throw new UnexpectedValueException(sprintf('Chat persistence field [%s] must be non-empty.', $key));
        }

        return $value;
    }

    /** @param array<mixed> $values */
    private function stringAllowEmpty(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_scalar($value)) {
            throw new UnexpectedValueException(sprintf('Chat persistence field [%s] must be a string.', $key));
        }

        return (string) $value;
    }

    /** @param array<mixed> $values */
    private function date(array $values, string $key): DateTimeImmutable
    {
        $value = $this->string($values, $key);

        return new DateTimeImmutable($value);
    }

    private function nullableDate(mixed $value): ?DateTimeImmutable
    {
        return is_scalar($value) && (string) $value !== '' ? new DateTimeImmutable((string) $value) : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<int, true>
     */
    private function integerSet(array $values): array
    {
        $set = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $set[(int) $value] = true;
            }
        }

        return $set;
    }
}
