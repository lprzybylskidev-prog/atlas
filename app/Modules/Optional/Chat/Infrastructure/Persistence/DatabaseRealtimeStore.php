<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Contracts\RealtimeStore;
use App\Modules\Optional\Chat\Application\DTOs\ConversationRealtimeState;
use App\Modules\Optional\Chat\Application\DTOs\ParticipantCursor;
use App\Modules\Optional\Chat\Application\DTOs\PresenceSummary;
use App\Modules\Optional\Chat\Domain\Realtime\ManualStatus;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use stdClass;
use UnexpectedValueException;

final readonly class DatabaseRealtimeStore implements RealtimeStore
{
    private const ONLINE_SECONDS = 90;

    private const HEARTBEAT_WRITE_SECONDS = 45;

    public function __construct(
        private ConnectionInterface $database,
        private UserLookup $users,
    ) {}

    public function state(int $conversationId, int $userId): ConversationRealtimeState
    {
        $row = $this->stateRow($conversationId, $userId);
        $lastReadId = $this->nullableInt($row, 'last_read_message_id');
        $unreadQuery = $this->database->table(ChatDatabaseTable::MESSAGES)
            ->where('conversation_id', $conversationId)
            ->where('author_user_id', '!=', $userId);

        if ($lastReadId !== null) {
            $unreadQuery->where('id', '>', $lastReadId);
        }

        $firstUnreadId = (clone $unreadQuery)->orderBy('id')->value('id');

        return new ConversationRealtimeState(
            lastDeliveredMessagePublicId: $this->messagePublicId($this->nullableInt($row, 'last_delivered_message_id')),
            lastReadMessagePublicId: $this->messagePublicId($lastReadId),
            firstUnreadMessagePublicId: $this->messagePublicId(is_numeric($firstUnreadId) ? (int) $firstUnreadId : null),
            unreadCount: $unreadQuery->count(),
        );
    }

    public function markDelivered(int $conversationId, int $userId, int $messageId): void
    {
        $this->advance($conversationId, $userId, 'last_delivered_message_id', $messageId);
    }

    public function markRead(int $conversationId, int $userId, int $messageId): void
    {
        $this->advance($conversationId, $userId, 'last_delivered_message_id', $messageId);
        $this->advance($conversationId, $userId, 'last_read_message_id', $messageId);
        $this->database->table(ChatDatabaseTable::CONVERSATION_REALTIME_STATES)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['unread_from_message_id' => null, 'updated_at' => now()]);
    }

    public function markUnread(int $conversationId, int $userId, int $messageId): void
    {
        $previousId = $this->database->table(ChatDatabaseTable::MESSAGES)
            ->where('conversation_id', $conversationId)
            ->where('id', '<', $messageId)
            ->max('id');
        $this->ensureState($conversationId, $userId);
        $this->database->table(ChatDatabaseTable::CONVERSATION_REALTIME_STATES)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update([
                'last_read_message_id' => is_numeric($previousId) ? (int) $previousId : null,
                'unread_from_message_id' => $messageId,
                'updated_at' => now(),
            ]);
    }

    public function totalUnread(int $userId): int
    {
        $total = 0;
        $conversationIds = $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->pluck('conversation_id');

        foreach ($conversationIds as $conversationId) {
            $total += $this->state($this->integer($conversationId), $userId)->unreadCount;
        }

        return $total;
    }

    public function participantCursors(int $conversationId): array
    {
        $rows = $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS.' as memberships')
            ->leftJoin(ChatDatabaseTable::CONVERSATION_REALTIME_STATES.' as states', static function (JoinClause $join): void {
                $join->on('states.conversation_id', '=', 'memberships.conversation_id')
                    ->on('states.user_id', '=', 'memberships.user_id');
            })
            ->where('memberships.conversation_id', $conversationId)
            ->whereNull('memberships.ended_at')
            ->orderBy('memberships.user_id')
            ->get(['memberships.user_id', 'states.last_delivered_message_id', 'states.last_read_message_id']);
        $result = [];

        foreach ($rows as $row) {
            $publicId = $this->users->publicIdForInternalId($this->integer(data_get($row, 'user_id')));

            if ($publicId !== null) {
                $result[] = new ParticipantCursor(
                    userPublicId: $publicId,
                    lastDeliveredMessagePublicId: $this->messagePublicId($this->nullableInt($row, 'last_delivered_message_id')),
                    lastReadMessagePublicId: $this->messagePublicId($this->nullableInt($row, 'last_read_message_id')),
                );
            }
        }

        return $result;
    }

    public function presenceForConversation(int $conversationId): array
    {
        $membershipUserIds = $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)
            ->where('conversation_id', $conversationId)
            ->whereNull('ended_at')
            ->pluck('user_id');
        $userIds = [];

        foreach ($membershipUserIds as $membershipUserId) {
            $userIds[] = $this->integer($membershipUserId);
        }

        $summaries = $this->users->displaySummariesForInternalIds($userIds);
        $presenceRows = $this->database->table(ChatDatabaseTable::USER_PRESENCE)->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $result = [];

        foreach ($summaries as $userId => $summary) {
            $row = $presenceRows->get($userId);
            $result[] = $this->presenceSummary($summary->publicId, $summary->name, $row instanceof stdClass ? $row : null);
        }

        return $result;
    }

    public function conversationPublicIdsForUser(int $userId): array
    {
        $values = $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS.' as memberships')
            ->join(ChatDatabaseTable::CONVERSATIONS.' as conversations', 'conversations.id', '=', 'memberships.conversation_id')
            ->where('memberships.user_id', $userId)
            ->whereNull('memberships.ended_at')
            ->orderBy('conversations.id')
            ->pluck('conversations.public_id');
        $publicIds = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new UnexpectedValueException('Invalid Chat conversation public identifier.');
            }

            $publicIds[] = $value;
        }

        return $publicIds;
    }

    public function heartbeat(int $userId): PresenceSummary
    {
        $now = now();
        $row = $this->database->table(ChatDatabaseTable::USER_PRESENCE)->where('user_id', $userId)->first();
        $lastHeartbeat = $row instanceof stdClass ? $this->date($row, 'last_heartbeat_at') : null;

        if ($lastHeartbeat === null || $lastHeartbeat->getTimestamp() <= $now->getTimestamp() - self::HEARTBEAT_WRITE_SECONDS) {
            $this->database->table(ChatDatabaseTable::USER_PRESENCE)->upsert([[
                'user_id' => $userId,
                'manual_status' => $row instanceof stdClass ? $this->string($row, 'manual_status') : ManualStatus::Available->value,
                'custom_text' => $row instanceof stdClass ? $this->nullableString($row, 'custom_text') : null,
                'custom_emoji' => $row instanceof stdClass ? $this->nullableString($row, 'custom_emoji') : null,
                'last_seen_at' => $now,
                'last_heartbeat_at' => $now,
                'created_at' => $row instanceof stdClass ? $this->date($row, 'created_at') : $now,
                'updated_at' => $now,
            ]], ['user_id'], ['last_seen_at', 'last_heartbeat_at', 'updated_at']);
        }

        return $this->presenceForUser($userId);
    }

    public function updateStatus(int $userId, ManualStatus $status, ?string $customText, ?string $customEmoji): PresenceSummary
    {
        $this->heartbeat($userId);
        $this->database->table(ChatDatabaseTable::USER_PRESENCE)->where('user_id', $userId)->update([
            'manual_status' => $status->value,
            'custom_text' => $customText,
            'custom_emoji' => $customEmoji,
            'updated_at' => now(),
        ]);

        return $this->presenceForUser($userId);
    }

    private function advance(int $conversationId, int $userId, string $column, int $messageId): void
    {
        $this->ensureState($conversationId, $userId);
        $row = $this->stateRow($conversationId, $userId);
        $current = $this->nullableInt($row, $column);

        if ($current === null || $messageId > $current) {
            $this->database->table(ChatDatabaseTable::CONVERSATION_REALTIME_STATES)
                ->where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->update([$column => $messageId, 'updated_at' => now()]);
        }
    }

    private function ensureState(int $conversationId, int $userId): void
    {
        $now = now();
        $this->database->table(ChatDatabaseTable::CONVERSATION_REALTIME_STATES)->insertOrIgnore([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function stateRow(int $conversationId, int $userId): stdClass
    {
        $this->ensureState($conversationId, $userId);

        return $this->database->table(ChatDatabaseTable::CONVERSATION_REALTIME_STATES)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first() ?? throw new UnexpectedValueException('Chat realtime state could not be loaded.');
    }

    private function presenceForUser(int $userId): PresenceSummary
    {
        $summary = $this->users->displaySummariesForInternalIds([$userId])[$userId] ?? throw new UnexpectedValueException('Chat presence user could not be loaded.');
        $row = $this->database->table(ChatDatabaseTable::USER_PRESENCE)->where('user_id', $userId)->first();

        return $this->presenceSummary($summary->publicId, $summary->name, $row instanceof stdClass ? $row : null);
    }

    private function presenceSummary(string $publicId, string $name, ?stdClass $row): PresenceSummary
    {
        $heartbeat = $row === null ? null : $this->date($row, 'last_heartbeat_at');

        return new PresenceSummary(
            userPublicId: $publicId,
            name: $name,
            online: $heartbeat !== null && $heartbeat->getTimestamp() > now()->getTimestamp() - self::ONLINE_SECONDS,
            lastSeenAt: $row === null ? null : $this->date($row, 'last_seen_at'),
            manualStatus: ManualStatus::from($row === null ? ManualStatus::Available->value : $this->string($row, 'manual_status')),
            customText: $row === null ? null : $this->nullableString($row, 'custom_text'),
            customEmoji: $row === null ? null : $this->nullableString($row, 'custom_emoji'),
        );
    }

    private function messagePublicId(?int $messageId): ?string
    {
        if ($messageId === null) {
            return null;
        }

        $value = $this->database->table(ChatDatabaseTable::MESSAGES)->where('id', $messageId)->value('public_id');

        return is_string($value) ? $value : null;
    }

    private function nullableInt(stdClass $row, string $key): ?int
    {
        $value = data_get($row, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    private function integer(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new UnexpectedValueException('Invalid Chat realtime integer value.');
        }

        return (int) $value;
    }

    private function string(stdClass $row, string $key): string
    {
        $value = data_get($row, $key);

        return is_string($value) ? $value : throw new UnexpectedValueException('Invalid Chat realtime string value.');
    }

    private function nullableString(stdClass $row, string $key): ?string
    {
        $value = data_get($row, $key);

        return is_string($value) ? $value : null;
    }

    private function date(stdClass $row, string $key): ?DateTimeImmutable
    {
        $value = data_get($row, $key);

        return is_string($value) ? new DateTimeImmutable($value) : null;
    }
}
