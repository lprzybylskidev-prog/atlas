<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRole;

final readonly class MeetingInvitationRecord
{
    public function __construct(
        public int $id,
        public int $meetingId,
        public int $userId,
        public MeetingRole $role,
        public MeetingResponse $response,
        public ?string $removedAt,
    ) {}
}
