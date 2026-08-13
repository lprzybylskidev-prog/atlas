<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\DTOs\ConversationMembershipRecord;
use App\Modules\Optional\Chat\Application\DTOs\ConversationRecord;
use App\Modules\Optional\Chat\Application\DTOs\ConversationTimelineEntry;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationMemberRole;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopeContext;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopePolicy;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;
use App\Modules\Optional\Chat\Domain\Conversations\Exceptions\ConversationNotFound;
use App\Modules\Optional\Chat\Domain\Conversations\Exceptions\ConversationOperationDenied;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Conversations\TimelineEntryType;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\TeamMembershipChangeParticipant;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use InvalidArgumentException;

final readonly class ConversationManager implements TeamMembershipChangeParticipant
{
    public function __construct(
        private ConversationStore $store,
        private ChatTransaction $transaction,
        private ChatModuleAccess $access,
        private UserLookup $users,
        private TeamLookup $teams,
        private UserTeamMembershipManager $teamMemberships,
        private ConversationScopePolicy $scopePolicy,
        private AuditRecorder $audit,
    ) {}

    public function startDirect(string $actorPublicId, string $targetPublicId, string $activeTeamPublicId): ConversationRecord
    {
        $this->access->ensureAllowed($actorPublicId, $activeTeamPublicId, ChatPermissionCatalog::DIRECT_CONVERSATION_STORE);
        [$actorId, $targetId] = $this->activeUserIds($actorPublicId, $targetPublicId);

        if ($actorId === $targetId) {
            throw new InvalidArgumentException('A direct conversation requires two different users.');
        }

        [$lowerUserId, $higherUserId] = $actorId < $targetId ? [$actorId, $targetId] : [$targetId, $actorId];

        return $this->transaction->run(function () use ($lowerUserId, $higherUserId): ConversationRecord {
            $existing = $this->store->findDirect($lowerUserId, $higherUserId);

            if ($existing !== null) {
                return $existing;
            }

            $conversation = $this->store->create(ConversationType::Direct, null, null, null, false);

            if (! $this->store->claimDirectPair($conversation->id, $lowerUserId, $higherUserId)) {
                $this->store->discardUnclaimed($conversation->id);

                return $this->store->findDirect($lowerUserId, $higherUserId) ?? throw new ConversationNotFound;
            }

            $this->store->addMembership($conversation->id, $lowerUserId, ConversationMemberRole::Member, ConversationType::Direct);
            $this->store->addMembership($conversation->id, $higherUserId, ConversationMemberRole::Member, ConversationType::Direct);

            return $conversation;
        });
    }

    /** @param list<string> $memberPublicIds */
    public function createGroup(string $actorPublicId, string $activeTeamPublicId, string $name, array $memberPublicIds = []): ConversationRecord
    {
        $this->access->ensureAllowed($actorPublicId, $activeTeamPublicId, ChatPermissionCatalog::GROUP_STORE);
        $name = $this->groupName($name);
        $memberPublicIds = array_values(array_unique(array_filter($memberPublicIds, static fn (string $id): bool => $id !== $actorPublicId)));
        $ids = $this->activeUserIds($actorPublicId, ...$memberPublicIds);
        $actorId = array_shift($ids);

        if (! is_int($actorId)) {
            throw ConversationOperationDenied::inactiveUser();
        }

        return $this->transaction->run(function () use ($actorPublicId, $actorId, $ids, $name): ConversationRecord {
            $conversation = $this->store->create(ConversationType::Group, $name, null, null, false);
            $this->store->addMembership($conversation->id, $actorId, ConversationMemberRole::Owner, ConversationType::Group);
            $this->store->appendTimeline($conversation->id, TimelineEntryType::GroupCreated, $actorId);

            foreach ($ids as $memberId) {
                $this->store->addMembership($conversation->id, $memberId, ConversationMemberRole::Member, ConversationType::Group);
                $this->store->appendTimeline($conversation->id, TimelineEntryType::GroupMemberAdded, $actorId, $memberId);
            }

            $this->auditGroup($actorPublicId, $conversation->publicId, 'chat.group.created', [], ['name' => $name, 'member_count' => count($ids) + 1]);

            return $conversation;
        });
    }

    public function renameGroup(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $name, ?string $avatarFilePublicId = null): void
    {
        $this->ensureChatUse($actorPublicId, $activeTeamPublicId);
        $actorId = $this->userId($actorPublicId);
        $name = $this->groupName($name);

        $this->transaction->run(function () use ($actorPublicId, $actorId, $conversationPublicId, $name, $avatarFilePublicId): void {
            $conversation = $this->lockedGroup($conversationPublicId);
            $this->ownerMembership($conversation, $actorId);
            $before = ['name' => $conversation->name];
            $this->store->updateGroupMetadata($conversation->id, $name, $avatarFilePublicId);
            $this->store->appendTimeline($conversation->id, TimelineEntryType::GroupMetadataChanged, $actorId, metadata: ['name_changed' => $conversation->name !== $name]);
            $this->auditGroup($actorPublicId, $conversation->publicId, 'chat.group.metadata_changed', $before, ['name' => $name]);
        });
    }

    public function addGroupMember(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $memberPublicId): void
    {
        $this->ensureChatUse($actorPublicId, $activeTeamPublicId);
        $actorId = $this->userId($actorPublicId);
        $memberId = $this->activeUserIds($memberPublicId)[0];

        $this->transaction->run(function () use ($actorPublicId, $actorId, $memberId, $memberPublicId, $conversationPublicId): void {
            $conversation = $this->lockedGroup($conversationPublicId);
            $this->ownerMembership($conversation, $actorId);

            if (! $this->store->addMembership($conversation->id, $memberId, ConversationMemberRole::Member, ConversationType::Group)) {
                return;
            }

            $this->store->appendTimeline($conversation->id, TimelineEntryType::GroupMemberAdded, $actorId, $memberId);
            $this->auditGroup($actorPublicId, $conversation->publicId, 'chat.group.member_added', [], ['member_public_id' => $memberPublicId]);
        });
    }

    public function removeGroupMember(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $memberPublicId): void
    {
        $this->ensureChatUse($actorPublicId, $activeTeamPublicId);
        $actorId = $this->userId($actorPublicId);
        $memberId = $this->userId($memberPublicId);

        $this->transaction->run(function () use ($actorPublicId, $actorId, $memberId, $memberPublicId, $conversationPublicId): void {
            $conversation = $this->lockedGroup($conversationPublicId);
            $this->ownerMembership($conversation, $actorId);
            $membership = $this->store->activeMembership($conversation->id, $memberId, true) ?? throw ConversationOperationDenied::notMember();

            if ($membership->role === ConversationMemberRole::Owner) {
                throw ConversationOperationDenied::invalidConversationType();
            }

            $this->store->endMembership($membership->id, 'removed');
            $this->store->appendTimeline($conversation->id, TimelineEntryType::GroupMemberRemoved, $actorId, $memberId);
            $this->auditGroup($actorPublicId, $conversation->publicId, 'chat.group.member_removed', [], ['member_public_id' => $memberPublicId]);
        });
    }

    public function transferGroupOwnership(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId, string $newOwnerPublicId): void
    {
        $this->ensureChatUse($actorPublicId, $activeTeamPublicId);
        $actorId = $this->userId($actorPublicId);
        $newOwnerId = $this->userId($newOwnerPublicId);

        $this->transaction->run(function () use ($actorPublicId, $actorId, $newOwnerId, $newOwnerPublicId, $conversationPublicId): void {
            $conversation = $this->lockedGroup($conversationPublicId);
            $owner = $this->ownerMembership($conversation, $actorId);
            $newOwner = $this->store->activeMembership($conversation->id, $newOwnerId, true) ?? throw ConversationOperationDenied::notMember();

            if ($newOwner->id === $owner->id) {
                return;
            }

            $this->store->changeRole($owner->id, ConversationMemberRole::Member);
            $this->store->changeRole($newOwner->id, ConversationMemberRole::Owner);
            $this->store->appendTimeline($conversation->id, TimelineEntryType::GroupOwnershipTransferred, $actorId, $newOwnerId);
            $this->auditGroup($actorPublicId, $conversation->publicId, 'chat.group.ownership_transferred', [], ['new_owner_public_id' => $newOwnerPublicId]);
        });
    }

    public function leaveGroup(string $actorPublicId, string $activeTeamPublicId, string $conversationPublicId): void
    {
        $this->ensureChatUse($actorPublicId, $activeTeamPublicId);
        $actorId = $this->userId($actorPublicId);

        $this->transaction->run(function () use ($actorPublicId, $actorId, $conversationPublicId): void {
            $conversation = $this->lockedGroup($conversationPublicId);
            $membership = $this->store->activeMembership($conversation->id, $actorId, true) ?? throw ConversationOperationDenied::notMember();
            $memberships = $this->store->activeMemberships($conversation->id, true);

            if ($membership->role === ConversationMemberRole::Owner && count($memberships) > 1) {
                throw ConversationOperationDenied::ownerMustTransfer();
            }

            $this->store->endMembership($membership->id, 'left');
            $this->store->appendTimeline($conversation->id, TimelineEntryType::GroupMemberLeft, $actorId, $actorId);
            $this->auditGroup($actorPublicId, $conversation->publicId, 'chat.group.member_left');

            if (count($memberships) === 1) {
                $this->store->close($conversation->id);
                $this->store->appendTimeline($conversation->id, TimelineEntryType::GroupClosed, $actorId);
                $this->auditGroup($actorPublicId, $conversation->publicId, 'chat.group.closed');
            }
        });
    }

    public function teamMembershipChanged(string $teamPublicId): void
    {
        $this->synchronizeTeamConversationRecord($teamPublicId);
    }

    public function synchronizeTeamConversationRecord(string $teamPublicId): ConversationRecord
    {
        $team = $this->teams->displaySummariesForPublicIds([$teamPublicId])[$teamPublicId] ?? null;

        if ($team === null) {
            throw new InvalidArgumentException('Team not found.');
        }

        $memberPublicIds = array_map(static fn ($membership): string => $membership->userPublicId, $this->teamMemberships->activeMembershipsForTeam($teamPublicId));
        $userIds = $this->users->internalIdsForPublicIds($memberPublicIds);

        return $this->transaction->run(function () use ($teamPublicId, $team, $userIds): ConversationRecord {
            $this->store->lockCanonicalKey('chat:team:'.$teamPublicId);
            $conversation = $this->store->findTeam($teamPublicId)
                ?? $this->store->create(ConversationType::Team, $team->name, $teamPublicId, null, true);
            $activeByUserId = [];

            foreach ($this->store->activeMemberships($conversation->id, true) as $membership) {
                $activeByUserId[$membership->userId] = $membership;
            }

            foreach ($userIds as $userId) {
                if (isset($activeByUserId[$userId])) {
                    unset($activeByUserId[$userId]);

                    continue;
                }

                $this->store->addMembership($conversation->id, $userId, ConversationMemberRole::Member, ConversationType::Team);
                $this->store->appendTimeline($conversation->id, TimelineEntryType::TeamMembershipSynchronized, subjectUserId: $userId, metadata: ['change' => 'added']);
            }

            foreach ($activeByUserId as $membership) {
                $this->store->endMembership($membership->id, 'team_membership_ended');
                $this->store->appendTimeline($conversation->id, TimelineEntryType::TeamMembershipSynchronized, subjectUserId: $membership->userId, metadata: ['change' => 'removed']);
            }

            return $conversation;
        });
    }

    public function ensureMeetingConversation(string $meetingPublicId, ?string $seriesPublicId, string $organizerPublicId): ConversationRecord
    {
        $ownerKey = $seriesPublicId ?? $meetingPublicId;
        $organizerId = $this->activeUserIds($organizerPublicId)[0];

        return $this->transaction->run(function () use ($ownerKey, $organizerId): ConversationRecord {
            $this->store->lockCanonicalKey('chat:meeting:'.$ownerKey);
            $existing = $this->store->findMeeting($ownerKey);

            if ($existing !== null) {
                return $existing;
            }

            $conversation = $this->store->create(ConversationType::Meeting, null, null, $ownerKey, true);
            $this->store->addMembership($conversation->id, $organizerId, ConversationMemberRole::Owner, ConversationType::Meeting, MeetingResponse::Accepted);
            $this->store->appendTimeline($conversation->id, TimelineEntryType::MeetingScheduled, $organizerId);

            return $conversation;
        });
    }

    public function inviteMeetingParticipant(string $conversationPublicId, string $actorPublicId, string $participantPublicId): void
    {
        $actorId = $this->userId($actorPublicId);
        $participantId = $this->activeUserIds($participantPublicId)[0];

        $this->transaction->run(function () use ($conversationPublicId, $actorId, $participantId): void {
            $conversation = $this->lockedMeeting($conversationPublicId);
            $this->requireActiveMembership($conversation, $actorId);

            if ($this->store->addMembership($conversation->id, $participantId, ConversationMemberRole::Member, ConversationType::Meeting, MeetingResponse::Pending)) {
                $this->store->appendTimeline($conversation->id, TimelineEntryType::MeetingParticipantInvited, $actorId, $participantId);
            }
        });
    }

    public function changeMeetingResponse(string $conversationPublicId, string $participantPublicId, MeetingResponse $response): void
    {
        $participantId = $this->userId($participantPublicId);

        $this->transaction->run(function () use ($conversationPublicId, $participantId, $response): void {
            $conversation = $this->lockedMeeting($conversationPublicId);
            $membership = $this->requireActiveMembership($conversation, $participantId);
            $this->store->changeMeetingResponse($membership->id, $response);
            $this->store->appendTimeline($conversation->id, TimelineEntryType::MeetingParticipantResponseChanged, $participantId, $participantId, ['response' => $response->value]);
        });
    }

    public function removeMeetingParticipant(string $conversationPublicId, string $actorPublicId, string $participantPublicId): void
    {
        $actorId = $this->userId($actorPublicId);
        $participantId = $this->userId($participantPublicId);

        $this->transaction->run(function () use ($conversationPublicId, $actorId, $participantId): void {
            $conversation = $this->lockedMeeting($conversationPublicId);
            $owner = $this->ownerMembership($conversation, $actorId);
            $participant = $this->requireActiveMembership($conversation, $participantId);

            if ($participant->id === $owner->id) {
                throw ConversationOperationDenied::invalidConversationType();
            }

            $this->store->endMembership($participant->id, 'meeting_removed');
            $this->store->appendTimeline($conversation->id, TimelineEntryType::MeetingParticipantRemoved, $actorId, $participantId);
        });
    }

    public function canAccess(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId): bool
    {
        if (! $this->access->allows($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::INDEX)) {
            return false;
        }

        $userId = $this->users->internalIdForPublicId($userPublicId);
        $conversation = $this->store->findByPublicId($conversationPublicId);

        if ($userId === null || $conversation === null) {
            return false;
        }

        $member = $this->store->hasActiveMembership($conversation->id, $userId);

        return $this->scopePolicy->allows(new ConversationScopeContext(
            type: $conversation->type,
            isConversationMember: $member,
            conversationTeamPublicId: $conversation->teamPublicId,
            activeTeamPublicId: $activeTeamPublicId,
            isMeetingParticipant: $conversation->type === ConversationType::Meeting && $member,
        ));
    }

    /** @return list<ConversationTimelineEntry> */
    public function timelineFor(string $userPublicId, string $activeTeamPublicId, string $conversationPublicId): array
    {
        if (! $this->canAccess($userPublicId, $activeTeamPublicId, $conversationPublicId)) {
            throw ConversationOperationDenied::notMember();
        }

        $conversation = $this->store->findByPublicId($conversationPublicId) ?? throw new ConversationNotFound;

        return $this->store->timeline($conversation->id);
    }

    private function lockedGroup(string $publicId): ConversationRecord
    {
        $conversation = $this->store->findByPublicId($publicId, true) ?? throw new ConversationNotFound;

        if ($conversation->type !== ConversationType::Group || $conversation->closed) {
            throw ConversationOperationDenied::invalidConversationType();
        }

        return $conversation;
    }

    private function lockedMeeting(string $publicId): ConversationRecord
    {
        $conversation = $this->store->findByPublicId($publicId, true) ?? throw new ConversationNotFound;

        if ($conversation->type !== ConversationType::Meeting) {
            throw ConversationOperationDenied::invalidConversationType();
        }

        return $conversation;
    }

    private function ownerMembership(ConversationRecord $conversation, int $userId): ConversationMembershipRecord
    {
        $membership = $this->requireActiveMembership($conversation, $userId);

        if ($membership->role !== ConversationMemberRole::Owner) {
            throw ConversationOperationDenied::notOwner();
        }

        return $membership;
    }

    private function requireActiveMembership(ConversationRecord $conversation, int $userId): ConversationMembershipRecord
    {
        return $this->store->activeMembership($conversation->id, $userId, true) ?? throw ConversationOperationDenied::notMember();
    }

    private function ensureChatUse(string $actorPublicId, string $activeTeamPublicId): void
    {
        $this->access->ensureAllowed($actorPublicId, $activeTeamPublicId, ChatPermissionCatalog::INDEX);
    }

    /** @return list<int> */
    private function activeUserIds(string ...$publicIds): array
    {
        $active = [];

        foreach ($this->users->allActiveDisplaySummaries() as $summary) {
            $active[$summary->publicId] = $this->users->internalIdForPublicId($summary->publicId);
        }

        $ids = [];

        foreach ($publicIds as $publicId) {
            $id = $active[$publicId] ?? null;

            if (! is_int($id)) {
                throw ConversationOperationDenied::inactiveUser();
            }

            $ids[] = $id;
        }

        return $ids;
    }

    private function userId(string $publicId): int
    {
        return $this->users->internalIdForPublicId($publicId) ?? throw ConversationOperationDenied::notMember();
    }

    private function groupName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 200) {
            throw new InvalidArgumentException('A group name must contain between 1 and 200 characters.');
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function auditGroup(string $actorPublicId, string $conversationPublicId, string $action, array $before = [], array $after = []): void
    {
        $this->audit->record(new AuditEvent(
            module: 'chat',
            action: $action,
            result: 'succeeded',
            source: 'application',
            actorPublicId: $actorPublicId,
            targetType: 'conversation',
            targetPublicId: $conversationPublicId,
            aggregateType: 'conversation',
            aggregatePublicId: $conversationPublicId,
            before: $before,
            after: $after,
        ));
    }
}
