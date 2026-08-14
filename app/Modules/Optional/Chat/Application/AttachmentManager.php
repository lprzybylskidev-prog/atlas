<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\Contracts\FileScanner;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Files\Application\Public\DTOs\DownloadableFile;
use App\Modules\Core\Files\Application\Public\Enums\FileScanState;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Contracts\AttachmentStore;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\MessageStore;
use App\Modules\Optional\Chat\Application\DTOs\ConversationContent;
use App\Modules\Optional\Chat\Application\DTOs\ConversationRecord;
use App\Modules\Optional\Chat\Application\DTOs\MessageAttachment;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Messages\AttachmentKind;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\MessageOperationDenied;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final readonly class AttachmentManager
{
    public const MAX_VOICE_SECONDS = 900;

    public function __construct(
        private AttachmentStore $attachments,
        private ConversationStore $conversations,
        private MessageStore $messages,
        private ConversationManager $conversationManager,
        private ChatModuleAccess $access,
        private UserLookup $users,
        private TeamLookup $teams,
        private FileStorage $files,
        private FileScanner $scanner,
        private FileLifecycle $lifecycle,
    ) {}

    public function upload(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, UploadedFile $file): MessageAttachment
    {
        return $this->store($actorPublicId, $activeTeamPublicId, $conversationPublicId, $file, AttachmentKind::File, null);
    }

    public function uploadVoice(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, UploadedFile $file, int $durationSeconds): MessageAttachment
    {
        if ($durationSeconds < 1 || $durationSeconds > self::MAX_VOICE_SECONDS) {
            throw new InvalidArgumentException('A Chat voice message must be between 1 and 900 seconds long.');
        }

        if (! str_starts_with((string) $file->getMimeType(), 'audio/') && $file->getMimeType() !== 'video/webm') {
            throw new InvalidArgumentException('A Chat voice message must contain audio.');
        }

        return $this->store($actorPublicId, $activeTeamPublicId, $conversationPublicId, $file, AttachmentKind::Voice, $durationSeconds);
    }

    public function status(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $attachmentPublicId): MessageAttachment
    {
        [$attachment] = $this->authorizedAttachment($actorPublicId, $activeTeamPublicId, $conversationPublicId, $attachmentPublicId);

        return $attachment;
    }

    public function retryScan(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $attachmentPublicId): MessageAttachment
    {
        [$attachment] = $this->authorizedAttachment($actorPublicId, $activeTeamPublicId, $conversationPublicId, $attachmentPublicId, true);
        $permission = $attachment->kind === AttachmentKind::Voice ? ChatPermissionCatalog::VOICE_MESSAGE_STORE : ChatPermissionCatalog::ATTACHMENT_STORE;
        $this->access->ensureAllowed($actorPublicId, $activeTeamPublicId, $permission);

        if (! in_array($attachment->scanState, [FileScanState::Pending, FileScanState::Failed], true)) {
            throw new InvalidArgumentException('This Chat attachment cannot be retried.');
        }

        $this->scanner->scanNow($attachment->filePublicId);

        return $this->attachments->findByPublicId($attachment->publicId) ?? throw MessageOperationDenied::unavailableMessage();
    }

    public function discard(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $attachmentPublicId): void
    {
        [$attachment, $actorId] = $this->authorizedAttachment($actorPublicId, $activeTeamPublicId, $conversationPublicId, $attachmentPublicId, true);
        $permission = $attachment->kind === AttachmentKind::Voice ? ChatPermissionCatalog::VOICE_MESSAGE_STORE : ChatPermissionCatalog::ATTACHMENT_STORE;
        $this->access->ensureAllowed($actorPublicId, $activeTeamPublicId, $permission);

        if ($attachment->messageId !== null || $attachment->discarded) {
            throw new InvalidArgumentException('Only an unsent Chat attachment can be discarded.');
        }

        $this->attachments->discard($attachment->id);
        $this->lifecycle->delete($attachment->filePublicId, $actorId, $this->teams->internalIdForPublicId($activeTeamPublicId), 'Discarded unsent Chat attachment.');
    }

    public function downloadable(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $attachmentPublicId): DownloadableFile
    {
        [$attachment, $actorId] = $this->authorizedAttachment($actorPublicId, $activeTeamPublicId, $conversationPublicId, $attachmentPublicId);

        if ($attachment->messageId === null || $attachment->discarded) {
            throw MessageOperationDenied::unavailableMessage();
        }

        return $this->files->cleanDownloadFile($attachment->filePublicId, $actorId, $this->teams->internalIdForPublicId($activeTeamPublicId));
    }

    public function content(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId): ConversationContent
    {
        [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId);
        $media = [];
        $files = [];

        foreach ($this->attachments->attachedForConversation($conversation->id) as $attachment) {
            if ($this->messages->isHiddenForUser((int) $attachment->messageId, $actorId)) {
                continue;
            }

            if ($attachment->kind === AttachmentKind::Voice || preg_match('#^(image|audio|video)/#', $attachment->mimeType) === 1) {
                $media[] = $attachment;
            } else {
                $files[] = $attachment;
            }
        }

        $links = [];
        foreach ($this->messages->conversationMessages($conversation->id) as $message) {
            if ($this->messages->isHiddenForUser($message->id, $actorId)) {
                continue;
            }

            preg_match_all('#https?://[^\s<>"\']+#iu', $message->body, $matches);
            foreach ($matches[0] as $link) {
                $links[] = rtrim((string) $link, '.,;:!?)]');
            }
        }

        return new ConversationContent($media, $files, array_values(array_unique($links)));
    }

    private function store(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, UploadedFile $file, AttachmentKind $kind, ?int $durationSeconds): MessageAttachment
    {
        [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId);
        $permission = $kind === AttachmentKind::Voice ? ChatPermissionCatalog::VOICE_MESSAGE_STORE : ChatPermissionCatalog::ATTACHMENT_STORE;
        $this->access->ensureAllowed($actorPublicId, $activeTeamPublicId, $permission);
        $stored = $this->files->storeUpload($file, $actorId, $this->teams->internalIdForPublicId($activeTeamPublicId), [
            'owner_type' => 'chat_attachment',
            'owner_public_id' => $conversationPublicId,
            'attachment_kind' => $kind->value,
        ]);

        $chatMimeType = $kind === AttachmentKind::Voice && $stored->mimeType === 'video/webm' ? 'audio/webm' : $stored->mimeType;

        return $this->attachments->create($conversation->id, $actorId, $stored->publicId, $kind, $stored->originalName, $chatMimeType, $stored->sizeBytes, $durationSeconds);
    }

    /** @return array{MessageAttachment, int} */
    private function authorizedAttachment(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $attachmentPublicId, bool $uploaderOnly = false): array
    {
        [$conversation, $actorId] = $this->participant($actorPublicId, $activeTeamPublicId, $conversationPublicId);
        $attachment = $this->attachments->findByPublicId($attachmentPublicId, $uploaderOnly) ?? throw MessageOperationDenied::unavailableMessage();

        if ($attachment->conversationId !== $conversation->id
            || ($attachment->messageId === null && $attachment->uploaderUserId !== $actorId)
            || ($uploaderOnly && $attachment->uploaderUserId !== $actorId)) {
            throw MessageOperationDenied::unavailableMessage();
        }

        if ($attachment->messageId !== null && $this->messages->isHiddenForUser($attachment->messageId, $actorId)) {
            throw MessageOperationDenied::unavailableMessage();
        }

        return [$attachment, $actorId];
    }

    /** @return array{ConversationRecord, int} */
    private function participant(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId): array
    {
        if (! $this->conversationManager->canAccess($actorPublicId, $activeTeamPublicId, $conversationPublicId)) {
            throw MessageOperationDenied::notParticipant();
        }

        $conversation = $this->conversations->findByPublicId($conversationPublicId) ?? throw MessageOperationDenied::notParticipant();
        $actorId = $this->users->internalIdForPublicId($actorPublicId) ?? throw MessageOperationDenied::notParticipant();

        return [$conversation, $actorId];
    }
}
