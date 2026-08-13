<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Conversations\ConversationMemberRole;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;

final readonly class ConversationMembershipRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $userId,
        public ConversationMemberRole $role,
        public ?MeetingResponse $meetingResponse,
    ) {}
}
