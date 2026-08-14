<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\ConversationRealtimeState;
use App\Modules\Optional\Chat\Application\DTOs\ParticipantCursor;
use App\Modules\Optional\Chat\Application\DTOs\PresenceSummary;
use App\Modules\Optional\Chat\Domain\Realtime\ManualStatus;

interface RealtimeStore
{
    public function state(int $conversationId, int $userId): ConversationRealtimeState;

    public function markDelivered(int $conversationId, int $userId, int $messageId): void;

    public function markRead(int $conversationId, int $userId, int $messageId): void;

    public function markUnread(int $conversationId, int $userId, int $messageId): void;

    public function totalUnread(int $userId): int;

    /** @return list<ParticipantCursor> */
    public function participantCursors(int $conversationId): array;

    /** @return list<PresenceSummary> */
    public function presenceForConversation(int $conversationId): array;

    /** @return list<string> */
    public function conversationPublicIdsForUser(int $userId): array;

    public function heartbeat(int $userId): PresenceSummary;

    public function updateStatus(int $userId, ManualStatus $status, ?string $customText, ?string $customEmoji): PresenceSummary;
}
