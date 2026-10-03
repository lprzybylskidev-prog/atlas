<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\ChatRetentionCandidate;
use DateTimeImmutable;

interface ChatRetentionStore
{
    /** @return list<ChatRetentionCandidate> */
    public function expiredMessages(DateTimeImmutable $cutoff, int $limit): array;

    public function deleteMessage(int $messageId): void;

    public function deleteTimelineEntries(DateTimeImmutable $cutoff, int $limit): int;

    /** @return array{messages:int,attachments:int,voiceMessages:int,oldestMessageAt:?string} */
    public function summary(?DateTimeImmutable $cutoff): array;

    public function retentionDays(): ?int;

    public function hasRetentionSetting(): bool;

    public function setRetentionDays(?int $days): void;
}
