<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Core\Files\Application\Public\Contracts\FileLookup;
use App\Modules\Core\Files\Application\Public\Enums\FileScanState;
use App\Modules\Optional\Chat\Application\Contracts\AttachmentStore;
use App\Modules\Optional\Chat\Application\DTOs\MessageAttachment;
use App\Modules\Optional\Chat\Domain\Messages\AttachmentKind;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use UnexpectedValueException;

final readonly class DatabaseAttachmentStore implements AttachmentStore
{
    public function __construct(
        private ConnectionInterface $database,
        private FileLookup $files,
    ) {}

    public function create(int $conversationId, int $uploaderUserId, string $filePublicId, AttachmentKind $kind, string $originalName, string $mimeType, int $sizeBytes, ?int $durationSeconds): MessageAttachment
    {
        $publicId = (string) Str::ulid();
        $id = $this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)->insertGetId([
            'public_id' => $publicId,
            'conversation_id' => $conversationId,
            'uploader_user_id' => $uploaderUserId,
            'file_public_id' => $filePublicId,
            'kind' => $kind->value,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'duration_seconds' => $durationSeconds,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findByPublicId($publicId) ?? throw new UnexpectedValueException(sprintf('Created Chat attachment [%d] could not be loaded.', $id));
    }

    public function findByPublicId(string $publicId, bool $lock = false): ?MessageAttachment
    {
        $query = $this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)->where('public_id', $publicId);

        return $this->attachment($lock ? $query->lockForUpdate()->first() : $query->first());
    }

    public function forMessage(int $messageId): array
    {
        return $this->rows($this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)->where('message_id', $messageId)->orderBy('id')->get()->all());
    }

    public function attachedForConversation(int $conversationId): array
    {
        return $this->rows($this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)
            ->where('conversation_id', $conversationId)
            ->whereNotNull('message_id')
            ->whereNull('discarded_at')
            ->orderByDesc('attached_at')
            ->orderByDesc('id')
            ->get()->all());
    }

    public function attachToMessage(array $attachmentIds, int $messageId): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $updated = $this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)
            ->whereIn('id', $attachmentIds)
            ->whereNull('message_id')
            ->whereNull('discarded_at')
            ->update(['message_id' => $messageId, 'attached_at' => now(), 'updated_at' => now()]);

        if ($updated !== count($attachmentIds)) {
            throw new UnexpectedValueException('One or more Chat attachments could not be attached to the message.');
        }
    }

    public function discard(int $attachmentId): void
    {
        $this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)
            ->where('id', $attachmentId)
            ->whereNull('message_id')
            ->whereNull('discarded_at')
            ->update(['discarded_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array<int, object>  $rows
     * @return list<MessageAttachment>
     */
    private function rows(array $rows): array
    {
        $attachments = [];

        foreach ($rows as $row) {
            $attachment = $this->attachment($row);

            if ($attachment !== null) {
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    private function attachment(?object $row): ?MessageAttachment
    {
        if (! is_object($row)) {
            return null;
        }

        $filePublicId = $this->string($row->file_public_id ?? null);
        $file = $this->files->status($filePublicId);

        return new MessageAttachment(
            id: $this->integer($row->id ?? null),
            publicId: $this->string($row->public_id ?? null),
            conversationId: $this->integer($row->conversation_id ?? null),
            uploaderUserId: $this->integer($row->uploader_user_id ?? null),
            messageId: is_numeric($row->message_id ?? null) ? (int) $row->message_id : null,
            filePublicId: $filePublicId,
            kind: AttachmentKind::from($this->string($row->kind ?? null)),
            originalName: $this->string($row->original_name ?? null),
            mimeType: $this->string($row->mime_type ?? null),
            sizeBytes: $this->integer($row->size_bytes ?? null),
            durationSeconds: is_numeric($row->duration_seconds ?? null) ? (int) $row->duration_seconds : null,
            scanState: $file?->deleted === false ? $file->scanState : FileScanState::Failed,
            discarded: ($row->discarded_at ?? null) !== null,
            createdAt: new DateTimeImmutable($this->string($row->created_at ?? null)),
        );
    }

    private function string(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new UnexpectedValueException('Chat attachment persistence returned an invalid string value.');
        }

        return $value;
    }

    private function integer(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new UnexpectedValueException('Chat attachment persistence returned an invalid integer value.');
        }

        return (int) $value;
    }
}
