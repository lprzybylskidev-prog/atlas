<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\MeetingInput;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInvitationRecord;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecord;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMutationScope;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRole;

interface MeetingStore
{
    public function lock(string $key): void;

    public function create(string $publicId, int $organizerUserId, string $conversationPublicId, MeetingInput $input): MeetingRecord;

    public function find(string $publicId, bool $forUpdate = false): ?MeetingRecord;

    /** @return list<MeetingRecord> */
    public function forUser(int $userId): array;

    /** @return list<MeetingInvitationRecord> */
    public function invitations(int $meetingId, bool $activeOnly = true): array;

    public function invitation(int $meetingId, int $userId, bool $forUpdate = false): ?MeetingInvitationRecord;

    public function invite(int $meetingId, int $userId, int $invitedByUserId, MeetingRole $role, MeetingResponse $response): MeetingInvitationRecord;

    public function respond(int $invitationId, MeetingResponse $response): void;

    public function remove(int $invitationId, int $removedByUserId): void;

    public function update(MeetingRecord $meeting, MeetingInput $input, MeetingMutationScope $scope, string $occurrenceDate): void;

    public function cancel(MeetingRecord $meeting, MeetingMutationScope $scope, string $occurrenceDate): void;

    /** @return list<array{effective_date:string,scope:string,cancelled:bool,payload:array<string,mixed>}> */
    public function mutations(int $meetingId): array;
}
