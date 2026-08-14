<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\MessageAttachment;
use App\Modules\Optional\Chat\Domain\Messages\AttachmentKind;

interface AttachmentStore
{
    public function create(
        int $conversationId,
        int $uploaderUserId,
        string $filePublicId,
        AttachmentKind $kind,
        string $originalName,
        string $mimeType,
        int $sizeBytes,
        ?int $durationSeconds,
    ): MessageAttachment;

    public function findByPublicId(string $publicId, bool $lock = false): ?MessageAttachment;

    /** @return list<MessageAttachment> */
    public function forMessage(int $messageId): array;

    /** @return list<MessageAttachment> */
    public function attachedForConversation(int $conversationId): array;

    /** @param list<int> $attachmentIds */
    public function attachToMessage(array $attachmentIds, int $messageId): void;

    public function discard(int $attachmentId): void;
}
