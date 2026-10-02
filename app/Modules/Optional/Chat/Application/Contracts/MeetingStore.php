<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\MeetingInput;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInvitationRecord;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecord;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRtcSession;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMutationScope;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRole;
use DateTimeImmutable;

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

    public function rtcSession(MeetingRecord $meeting, string $occurrenceDate, bool $forUpdate = false): ?MeetingRtcSession;

    public function startRtcSession(int $occurrenceId, string $roomName): MeetingRtcSession;

    public function joinRtcParticipant(int $occurrenceId, int $userId, bool $cameraEnabled, bool $microphoneEnabled): void;

    public function leaveRtcParticipant(int $occurrenceId, int $userId): void;

    public function setRtcParticipantMedia(int $occurrenceId, int $userId, bool $cameraEnabled, bool $microphoneEnabled): void;

    public function setRtcParticipantScreenShare(int $occurrenceId, int $userId, bool $active): void;

    public function setRtcParticipantMicrophoneAllowed(int $occurrenceId, int $userId, bool $allowed): void;

    public function banRtcParticipant(int $occurrenceId, int $userId): void;

    public function setRtcLocked(int $occurrenceId, bool $locked): void;

    public function endRtcSession(int $occurrenceId): void;

    /** @return list<array{userId:int,joinedAt:string,leftAt:?string,durationSeconds:?int,occurrenceDate:string}> */
    public function attendance(MeetingRecord $meeting): array;

    /** @return list<string> RTC room names ended after 15 continuous empty minutes. */
    public function endExpiredEmptyRtcSessions(DateTimeImmutable $now): array;
}
