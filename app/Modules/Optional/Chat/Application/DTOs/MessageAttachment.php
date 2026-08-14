<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Core\Files\Application\Public\Enums\FileScanState;
use App\Modules\Optional\Chat\Domain\Messages\AttachmentKind;
use DateTimeImmutable;

final readonly class MessageAttachment
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $conversationId,
        public int $uploaderUserId,
        public ?int $messageId,
        public string $filePublicId,
        public AttachmentKind $kind,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
        public ?int $durationSeconds,
        public FileScanState $scanState,
        public bool $discarded,
        public DateTimeImmutable $createdAt,
    ) {}

    public function available(): bool
    {
        return ! $this->discarded && $this->scanState === FileScanState::Clean;
    }
}
