<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Support;

use App\Modules\Optional\Chat\Application\DTOs\ConversationRealtimeState;
use App\Modules\Optional\Chat\Application\DTOs\ParticipantCursor;
use App\Modules\Optional\Chat\Application\DTOs\PresenceSummary;
use App\Modules\Optional\Chat\Application\DTOs\VisibleMessage;

final class ChatRealtimePayload
{
    /** @return array<string, mixed> */
    public static function message(VisibleMessage $message): array
    {
        return [
            'publicId' => $message->publicId,
            'authorPublicId' => $message->authorPublicId,
            'body' => $message->body,
            'renderedHtml' => $message->renderedHtml,
            'replyToMessagePublicId' => $message->replyToMessagePublicId,
            'forwarded' => $message->forwarded,
            'version' => $message->version,
            'edited' => $message->edited,
            'deletedForViewer' => $message->deletedForViewer,
            'createdAt' => $message->createdAt->format(DATE_ATOM),
            'attachments' => array_map(static fn ($attachment): array => [
                'publicId' => $attachment->publicId,
                'kind' => $attachment->kind->value,
                'name' => $attachment->originalName,
                'mimeType' => $attachment->mimeType,
                'sizeBytes' => $attachment->sizeBytes,
                'durationSeconds' => $attachment->durationSeconds,
                'scanState' => $attachment->scanState->value,
                'available' => $attachment->available(),
            ], $message->attachments),
        ];
    }

    /** @return array<string, mixed> */
    public static function state(ConversationRealtimeState $state): array
    {
        return [
            'lastDeliveredMessagePublicId' => $state->lastDeliveredMessagePublicId,
            'lastReadMessagePublicId' => $state->lastReadMessagePublicId,
            'firstUnreadMessagePublicId' => $state->firstUnreadMessagePublicId,
            'unreadCount' => $state->unreadCount,
        ];
    }

    /** @return array<string, mixed> */
    public static function presence(PresenceSummary $presence): array
    {
        return [
            'userPublicId' => $presence->userPublicId,
            'name' => $presence->name,
            'online' => $presence->online,
            'lastSeenAt' => $presence->lastSeenAt?->format(DATE_ATOM),
            'manualStatus' => $presence->manualStatus->value,
            'customText' => $presence->customText,
            'customEmoji' => $presence->customEmoji,
        ];
    }

    /** @return array<string, mixed> */
    public static function cursor(ParticipantCursor $cursor): array
    {
        return [
            'userPublicId' => $cursor->userPublicId,
            'lastDeliveredMessagePublicId' => $cursor->lastDeliveredMessagePublicId,
            'lastReadMessagePublicId' => $cursor->lastReadMessagePublicId,
        ];
    }
}
