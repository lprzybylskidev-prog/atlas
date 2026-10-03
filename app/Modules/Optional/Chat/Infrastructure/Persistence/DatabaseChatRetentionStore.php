<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Optional\Chat\Application\Contracts\ChatRetentionStore;
use App\Modules\Optional\Chat\Application\DTOs\ChatRetentionCandidate;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseChatRetentionStore implements ChatRetentionStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function expiredMessages(DateTimeImmutable $cutoff, int $limit): array
    {
        $rows = $this->database->table(ChatDatabaseTable::MESSAGES)
            ->where('created_at', '<=', $cutoff->format(DATE_ATOM))
            ->orderBy('created_at')->orderBy('id')->limit($limit)->get(['id', 'public_id']);
        $result = [];
        foreach ($rows as $row) {
            $attachments = [];
            foreach ($this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)->where('message_id', $row->id)->get(['public_id', 'file_public_id']) as $attachment) {
                $attachments[] = ['publicId' => $this->string($attachment->public_id ?? null), 'filePublicId' => $this->string($attachment->file_public_id ?? null)];
            }
            $result[] = new ChatRetentionCandidate($this->integer($row->id ?? null), $this->string($row->public_id ?? null), $attachments);
        }

        return $result;
    }

    public function deleteMessage(int $messageId): void
    {
        $tables = [
            ChatDatabaseTable::MESSAGE_EDIT_HISTORY,
            ChatDatabaseTable::MESSAGE_DELETIONS,
            ChatDatabaseTable::MESSAGE_REACTIONS,
            ChatDatabaseTable::MESSAGE_MENTIONS,
            ChatDatabaseTable::MESSAGE_PINS,
            ChatDatabaseTable::MESSAGE_BOOKMARKS,
        ];
        foreach ($tables as $table) {
            $this->database->table($table)->where('message_id', $messageId)->delete();
        }
        $this->database->table(ChatDatabaseTable::MESSAGE_DRAFTS)->where('reply_to_message_id', $messageId)->update(['reply_to_message_id' => null, 'updated_at' => now()]);
        foreach (['last_delivered_message_id', 'last_read_message_id', 'unread_from_message_id'] as $column) {
            $this->database->table(ChatDatabaseTable::CONVERSATION_REALTIME_STATES)->where($column, $messageId)->update([$column => null, 'updated_at' => now()]);
        }
        $this->database->table(ChatDatabaseTable::MESSAGES)->where('reply_to_message_id', $messageId)->update(['reply_to_message_id' => null, 'updated_at' => now()]);
        $this->database->table(ChatDatabaseTable::MESSAGES)->where('forwarded_from_message_id', $messageId)->update(['forwarded_from_message_id' => null, 'updated_at' => now()]);
        $this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)->where('message_id', $messageId)->delete();
        $this->database->table(ChatDatabaseTable::MESSAGES)->where('id', $messageId)->delete();
    }

    public function deleteTimelineEntries(DateTimeImmutable $cutoff, int $limit): int
    {
        $ids = $this->database->table(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES)
            ->where('occurred_at', '<=', $cutoff->format(DATE_ATOM))->orderBy('id')->limit($limit)->pluck('id')->all();

        return $ids === [] ? 0 : $this->database->table(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES)->whereIn('id', $ids)->delete();
    }

    public function summary(?DateTimeImmutable $cutoff): array
    {
        $messages = $this->database->table(ChatDatabaseTable::MESSAGES);
        if ($cutoff !== null) {
            $messages->where('created_at', '<=', $cutoff->format(DATE_ATOM));
        }
        $ids = (clone $messages)->pluck('id');
        $attachments = $this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)->whereIn('message_id', $ids);

        return [
            'messages' => (clone $messages)->count(),
            'attachments' => (clone $attachments)->count(),
            'voiceMessages' => (clone $attachments)->where('kind', 'voice')->count(),
            'oldestMessageAt' => is_string($oldest = (clone $messages)->min('created_at')) ? $oldest : null,
        ];
    }

    public function retentionDays(): ?int
    {
        $value = $this->database->table(ChatDatabaseTable::SETTINGS)->where('key', 'message_retention_days')->value('value');
        if (! is_string($value)) {
            return null;
        }
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        return is_int($decoded) && $decoded > 0 ? $decoded : null;
    }

    public function hasRetentionSetting(): bool
    {
        return $this->database->table(ChatDatabaseTable::SETTINGS)->where('key', 'message_retention_days')->exists();
    }

    public function setRetentionDays(?int $days): void
    {
        $this->database->table(ChatDatabaseTable::SETTINGS)->updateOrInsert(
            ['key' => 'message_retention_days'],
            ['value' => json_encode($days, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function integer(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new \UnexpectedValueException('Chat retention persistence returned an invalid integer.');
        }

        return (int) $value;
    }

    private function string(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new \UnexpectedValueException('Chat retention persistence returned an invalid string.');
        }

        return $value;
    }
}
