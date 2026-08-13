<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\ConversationMembershipRecord;
use App\Modules\Optional\Chat\Application\DTOs\ConversationRecord;
use App\Modules\Optional\Chat\Application\DTOs\ConversationTimelineEntry;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationMemberRole;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Conversations\TimelineEntryType;

interface ConversationStore
{
    public function lockCanonicalKey(string $key): void;

    public function findByPublicId(string $publicId, bool $lock = false): ?ConversationRecord;

    public function findDirect(int $lowerUserId, int $higherUserId): ?ConversationRecord;

    public function findTeam(string $teamPublicId): ?ConversationRecord;

    public function findMeeting(string $meetingOwnerKey): ?ConversationRecord;

    public function create(ConversationType $type, ?string $name, ?string $teamPublicId, ?string $meetingOwnerKey, bool $systemOwned): ConversationRecord;

    public function claimDirectPair(int $conversationId, int $lowerUserId, int $higherUserId): bool;

    public function discardUnclaimed(int $conversationId): void;

    public function activeMembership(int $conversationId, int $userId, bool $lock = false): ?ConversationMembershipRecord;

    /** @return list<ConversationMembershipRecord> */
    public function activeMemberships(int $conversationId, bool $lock = false): array;

    public function addMembership(int $conversationId, int $userId, ConversationMemberRole $role, ConversationType $source, ?MeetingResponse $meetingResponse = null): bool;

    public function endMembership(int $membershipId, string $reason): void;

    public function changeRole(int $membershipId, ConversationMemberRole $role): void;

    public function changeMeetingResponse(int $membershipId, MeetingResponse $response): void;

    public function updateGroupMetadata(int $conversationId, string $name, ?string $avatarFilePublicId): void;

    public function close(int $conversationId): void;

    /** @param array<string, scalar|null> $metadata */
    public function appendTimeline(int $conversationId, TimelineEntryType $type, ?int $actorUserId = null, ?int $subjectUserId = null, array $metadata = []): void;

    /** @return list<ConversationTimelineEntry> */
    public function timeline(int $conversationId): array;

    public function hasActiveMembership(int $conversationId, int $userId): bool;
}
